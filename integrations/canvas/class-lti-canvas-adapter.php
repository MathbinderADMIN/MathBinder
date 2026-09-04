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
        if(($claims['https://purl.imsglobal.org/spec/lti/claim/version']??'')!=='1.3.0') return new WP_Error('mb_lti_version','Canvas launch must use LTI version 1.3.0.');
        $target=esc_url_raw((string)($claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri']??''));
        if($target===''||empty($saved['target'])||!hash_equals((string)$saved['target'],$target)) return new WP_Error('mb_lti_target','Canvas target-link claim does not match the initiated launch.');
        if(!empty($saved['issuer'])&&!hash_equals((string)$saved['issuer'],rtrim((string)($claims['iss']??''),'/'))) return new WP_Error('mb_lti_state_issuer','Canvas issuer changed during the launch.');
        if(!empty($saved['client_id'])&&!in_array((string)$saved['client_id'],array_map('strval',(array)($claims['aud']??[])),true)) return new WP_Error('mb_lti_state_client','Canvas client changed during the launch.');
        if($message==='LtiResourceLinkRequest'&&empty($claims['https://purl.imsglobal.org/spec/lti/claim/resource_link']['id'])) return new WP_Error('mb_lti_resource','Canvas resource-link launch is missing its resource ID.');
        if($message==='LtiDeepLinkingRequest'&&empty($claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings']['deep_link_return_url'])) return new WP_Error('mb_lti_deep_link','Canvas Deep Linking launch is missing its return URL.');
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
        $payload=['userId'=>(string)$score['user_id'],'scoreGiven'=>(float)$score['score'],'scoreMaximum'=>(float)($score['maximum']??100),'activityProgress'=>'Completed','gradingProgress'=>'FullyGraded','timestamp'=>gmdate('c')]; if(!empty($score['comment']))$payload['comment']=sanitize_textarea_field($score['comment']);
        return $this->service_request($url,'POST',$payload,['https://purl.imsglobal.org/spec/lti-ags/scope/score']);
    }
    public function submit_activity(array $submission){
        $url=esc_url_raw($submission['scores_url']??''); if(!$url)return new WP_Error('mb_canvas_scores_url','Canvas did not provide a score endpoint for this assignment.');
        $user_id=sanitize_text_field((string)($submission['user_id']??'')); if($user_id==='')return new WP_Error('mb_canvas_submission_user','Canvas did not provide a student identifier.');
        $payload=['userId'=>$user_id,'activityProgress'=>'Completed','gradingProgress'=>'PendingManual','timestamp'=>gmdate('c')];
        if(!empty($submission['comment']))$payload['comment']=sanitize_textarea_field($submission['comment']);
        return $this->service_request($url,'POST',$payload,['https://purl.imsglobal.org/spec/lti-ags/scope/score']);
    }
    public function create_evidence_handoff(array $evidence){
        if(empty($evidence['student_id'])||empty($evidence['assignment_id'])) return new WP_Error('mb_canvas_evidence','Evidence handoff requires a student and assignment.');
        return add_query_arg(['student'=>absint($evidence['student_id']),'assignment'=>sanitize_text_field($evidence['assignment_id']),'canvas_handoff'=>1],home_url('/evidence-folder/'));
    }
    public function create_line_item($url,array $item){ return $this->service_request($url,'POST',$item,['https://purl.imsglobal.org/spec/lti-ags/scope/lineitem']); }

    private function service_get($url,array $scopes){ return $this->service_request($url,'GET',null,$scopes); }
    private function service_request($url,$method,$body,array $scopes){
        if(!$this->trusted_service_url($url)) return new WP_Error('mb_canvas_service_host','Canvas service URL does not match the configured instance.');
        $token=$this->access_token($scopes); if(is_wp_error($token))return $token;
        $args=['timeout'=>30,'redirection'=>0,'method'=>$method,'headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']];
        if($body!==null){$args['headers']['Content-Type']=in_array('https://purl.imsglobal.org/spec/lti-ags/scope/score',$scopes,true)?'application/vnd.ims.lis.v1.score+json':'application/vnd.ims.lis.v2.lineitem+json';$args['body']=wp_json_encode($body);}
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
    private function trusted_service_url($url){$service=wp_parse_url($url);$canvas=wp_parse_url($this->settings['canvas_url']);return is_array($service)&&is_array($canvas)&&strtolower((string)($service['scheme']??''))==='https'&&strtolower((string)($service['host']??''))===strtolower((string)($canvas['host']??''))&&absint($service['port']??443)===absint($canvas['port']??443);}
}
