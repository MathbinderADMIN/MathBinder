<?php
if (!defined('ABSPATH')) exit;

/** Authenticated LTI 1.3 Advantage sandbox adapter. */
final class MathBinder_LTI_Canvas_Adapter implements MathBinder_Canvas_Adapter {
    private $settings;
    public function __construct(){ $this->settings=MathBinder_Canvas_Settings::get(); }
    public function is_configured(){ return MathBinder_Canvas_Settings::is_complete($this->settings); }

    public function launch(array $request) {
        $state=sanitize_text_field($request['state']??''); $id_token=(string)($request['id_token']??'');
        $saved=get_transient('mb_lti_state_'.hash('sha256',$state)); delete_transient('mb_lti_state_'.hash('sha256',$state));
        if(!is_array($saved)) return new WP_Error('mb_lti_state','The LTI login state is invalid or expired.');
        $claims=MathBinder_Canvas_Crypto::verify_canvas_jwt($id_token,$this->settings,$saved['nonce']);
        if(is_wp_error($claims)) return $claims;
        $message=$claims['https://purl.imsglobal.org/spec/lti/claim/message_type']??'';
        if(!in_array($message,['LtiResourceLinkRequest','LtiDeepLinkingRequest'],true)) return new WP_Error('mb_lti_message','This Canvas launch type is not supported.');
        return ['claims'=>$claims,'message_type'=>$message,'roles'=>(array)($claims['https://purl.imsglobal.org/spec/lti/claim/roles']??[]),'context'=>(array)($claims['https://purl.imsglobal.org/spec/lti/claim/context']??[]),'resource_link'=>(array)($claims['https://purl.imsglobal.org/spec/lti/claim/resource_link']??[]),'services'=>['ags'=>(array)($claims['https://purl.imsglobal.org/spec/lti-ags/claim/endpoint']??[]),'nrps'=>(array)($claims['https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice']??[])]];
    }
    public function sync_roster($context_id){ return $this->service_get((string)$context_id,['https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly']); }
    public function create_deep_link(array $resource){
        $now=time(); $claims=['iss'=>(string)$this->settings['client_id'],'aud'=>(string)($resource['aud']??$this->settings['canvas_url']),'iat'=>$now,'exp'=>$now+300,'nonce'=>wp_generate_uuid4(),'https://purl.imsglobal.org/spec/lti/claim/deployment_id'=>$this->settings['deployment_id'],'https://purl.imsglobal.org/spec/lti/claim/message_type'=>'LtiDeepLinkingResponse','https://purl.imsglobal.org/spec/lti/claim/version'=>'1.3.0','https://purl.imsglobal.org/spec/lti-dl/claim/data'=>(string)($resource['data']??''),'https://purl.imsglobal.org/spec/lti-dl/claim/content_items'=>(array)($resource['content_items']??[])];
        return MathBinder_Canvas_Crypto::sign($claims,$this->settings);
    }
    public function pass_grade(array $score){
        if(empty($score['teacher_approved'])) return new WP_Error('mb_canvas_teacher_approval','A teacher must approve the score before Canvas grade passback.');
        $url=esc_url_raw($score['scores_url']??''); if(!$url)return new WP_Error('mb_canvas_scores_url','Canvas did not provide a score endpoint.');
        $payload=['userId'=>(string)$score['user_id'],'scoreGiven'=>(float)$score['score'],'scoreMaximum'=>(float)($score['maximum']??100),'activityProgress'=>'Completed','gradingProgress'=>'FullyGraded','timestamp'=>gmdate('c')];
        return $this->service_request($url,'POST',$payload,['https://purl.imsglobal.org/spec/lti-ags/scope/score']);
    }
    public function create_evidence_handoff(array $evidence){
        if(empty($evidence['student_id'])||empty($evidence['assignment_id'])) return new WP_Error('mb_canvas_evidence','Evidence handoff requires a student and assignment.');
        return add_query_arg(['student'=>absint($evidence['student_id']),'assignment'=>sanitize_text_field($evidence['assignment_id']),'canvas_handoff'=>1],home_url('/evidence-folder/'));
    }
    public function create_line_item($url,array $item){ return $this->service_request($url,'POST',$item,['https://purl.imsglobal.org/spec/lti-ags/scope/lineitem']); }

    private function service_get($url,array $scopes){ return $this->service_request($url,'GET',null,$scopes); }
    private function service_request($url,$method,$body,array $scopes){
        if(stripos($url,rtrim($this->settings['canvas_url'],'/'))!==0) return new WP_Error('mb_canvas_service_host','Canvas service URL does not match the configured instance.');
        $token=$this->access_token($scopes); if(is_wp_error($token))return $token;
        $args=['timeout'=>30,'redirection'=>0,'method'=>$method,'headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']];
        if($body!==null){$args['headers']['Content-Type']='application/vnd.ims.lis.v2.lineitem+json';$args['body']=wp_json_encode($body);}
        $response=wp_safe_remote_request($url,$args);if(is_wp_error($response))return $response;
        $code=wp_remote_retrieve_response_code($response);$decoded=json_decode(wp_remote_retrieve_body($response),true);
        if($code<200||$code>=300)return new WP_Error('mb_canvas_service','Canvas service request failed.',['status'=>$code]);
        return is_array($decoded)?$decoded:[];
    }
    private function access_token(array $scopes){
        $cache_key='mb_canvas_token_'.md5(implode(' ',$scopes));$cached=get_transient($cache_key);if(is_string($cached)&&$cached!=='')return $cached;
        $now=time();$assertion=MathBinder_Canvas_Crypto::sign(['iss'=>(string)$this->settings['client_id'],'sub'=>(string)$this->settings['client_id'],'aud'=>(string)$this->settings['canvas_token_url'],'iat'=>$now,'exp'=>$now+300,'jti'=>wp_generate_uuid4()],$this->settings);if(is_wp_error($assertion))return $assertion;
        $response=wp_safe_remote_post($this->settings['canvas_token_url'],['timeout'=>20,'redirection'=>0,'body'=>['grant_type'=>'client_credentials','client_assertion_type'=>'urn:ietf:params:oauth:client-assertion-type:jwt-bearer','client_assertion'=>$assertion,'scope'=>implode(' ',$scopes)]]);
        if(is_wp_error($response))return $response;$body=json_decode(wp_remote_retrieve_body($response),true);
        if(wp_remote_retrieve_response_code($response)!==200||empty($body['access_token']))return new WP_Error('mb_canvas_token','Canvas service authorization failed.');
        set_transient($cache_key,$body['access_token'],max(60,(int)($body['expires_in']??3600)-60));return $body['access_token'];
    }
}
