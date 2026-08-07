<?php
if (!defined('ABSPATH')) exit;

/** Public LTI endpoints plus administrator-only diagnostics. Fail closed. */
final class MathBinder_Canvas_Transport {
    const REST_NAMESPACE='mathbinder/v1';
    public static function register(){add_action('rest_api_init',[__CLASS__,'routes']);add_filter('mathbinder_canvas_adapter_ready',[__CLASS__,'adapter_ready']);}
    public static function adapter_ready(){return class_exists('MathBinder_LTI_Canvas_Adapter')&&function_exists('openssl_verify');}
    public static function routes(){
        register_rest_route(self::REST_NAMESPACE,'/canvas/config',['methods'=>'GET','callback'=>[__CLASS__,'config'],'permission_callback'=>'__return_true']);
        register_rest_route(self::REST_NAMESPACE,'/canvas/jwks',['methods'=>'GET','callback'=>[__CLASS__,'jwks'],'permission_callback'=>'__return_true']);
        register_rest_route(self::REST_NAMESPACE,'/canvas/oidc/login',['methods'=>['GET','POST'],'callback'=>[__CLASS__,'oidc'],'permission_callback'=>'__return_true']);
        register_rest_route(self::REST_NAMESPACE,'/canvas/lti/launch',['methods'=>'POST','callback'=>[__CLASS__,'launch'],'permission_callback'=>'__return_true']);
        register_rest_route(self::REST_NAMESPACE,'/canvas/status',['methods'=>'GET','callback'=>[__CLASS__,'status'],'permission_callback'=>[__CLASS__,'admin_permission']]);
    }
    public static function config(){
        $base=rest_url(self::REST_NAMESPACE.'/canvas/');
        return ['title'=>'MathBinder','description'=>'California standards-aligned mastery paths, evidence, and progress','oidc_initiation_url'=>$base.'oidc/login','target_link_uri'=>$base.'lti/launch','public_jwk_url'=>$base.'jwks','scopes'=>['https://purl.imsglobal.org/spec/lti-ags/scope/lineitem','https://purl.imsglobal.org/spec/lti-ags/scope/score','https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly'],'extensions'=>[['platform'=>'canvas.instructure.com','settings'=>['placements'=>[['placement'=>'course_navigation','message_type'=>'LtiResourceLinkRequest','target_link_uri'=>$base.'lti/launch'],['placement'=>'assignment_selection','message_type'=>'LtiDeepLinkingRequest','target_link_uri'=>$base.'lti/launch']]]]],'public_jwk'=>json_decode((string)(MathBinder_Canvas_Settings::get()['public_jwk']??'{}'),true)];
    }
    public static function jwks(){ $j=json_decode((string)(MathBinder_Canvas_Settings::get()['public_jwk']??''),true);return ['keys'=>is_array($j)&&!empty($j['kty'])?[$j]:[]]; }
    public static function oidc(WP_REST_Request $request){
        if(!self::gates_pass())return new WP_Error('mb_canvas_disabled','Canvas LTI sandbox is not authorized. No data was transmitted.',['status'=>503]);
        $s=MathBinder_Canvas_Settings::get();$iss=rtrim((string)$request->get_param('iss'),'/');$login_hint=(string)$request->get_param('login_hint');$target=esc_url_raw((string)$request->get_param('target_link_uri'));
        if($iss!==rtrim($s['canvas_url'],'/')||$login_hint===''||strpos($target,rest_url(self::REST_NAMESPACE.'/canvas/'))!==0)return new WP_Error('mb_lti_login','Invalid Canvas login initiation.',['status'=>400]);
        $state=MathBinder_Canvas_Crypto::b64url_encode(random_bytes(32));$nonce=MathBinder_Canvas_Crypto::b64url_encode(random_bytes(32));set_transient('mb_lti_state_'.hash('sha256',$state),['nonce'=>$nonce,'target'=>$target],10*MINUTE_IN_SECONDS);
        $url=add_query_arg(['scope'=>'openid','response_type'=>'id_token','response_mode'=>'form_post','prompt'=>'none','client_id'=>$s['client_id'],'redirect_uri'=>rest_url(self::REST_NAMESPACE.'/canvas/lti/launch'),'login_hint'=>$login_hint,'state'=>$state,'nonce'=>$nonce,'lti_message_hint'=>(string)$request->get_param('lti_message_hint')],$s['canvas_auth_url']);
        wp_safe_redirect($url);exit;
    }
    public static function launch(WP_REST_Request $request){
        if(!self::gates_pass())return new WP_Error('mb_canvas_disabled','Canvas LTI sandbox is not authorized.',['status'=>503]);
        $result=MathBinder_Canvas_Integration::adapter()->launch($request->get_params());if(is_wp_error($result)){MathBinder_Audit_Log::record('blocked','canvas_launch',0,['reason'=>$result->get_error_code()]);return $result;}
        $claims=$result['claims'];$subject=(string)($claims['sub']??'');$context=(array)$result['context'];
        MathBinder_Canvas_Repository::save_mapping('context',(string)($context['id']??''),'class',0,['label'=>sanitize_text_field($context['label']??''),'title'=>sanitize_text_field($context['title']??'')],MathBinder_Canvas_Settings::get(),'pending_review');
        MathBinder_Audit_Log::record('verified','canvas_launch',0,['message_type'=>$result['message_type'],'subject_hash'=>hash('sha256',$subject)]);
        return ['verified'=>true,'message_type'=>$result['message_type'],'mapping_status'=>'pending_review','message'=>'Canvas launch verified. An authorized teacher or administrator must map this course and identity before records synchronize.'];
    }
    public static function status(){return MathBinder_Canvas_Integration::status();}
    public static function admin_permission(){return current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS);}
    private static function gates_pass(){ $s=MathBinder_Canvas_Settings::get();$r=MathBinder_Canvas_Protocol::readiness();return ($s['operating_mode']??'disabled')==='sandbox'&&!empty($r['configuration_complete'])&&!empty($r['locally_validated'])&&!empty($r['activation_gate_enabled'])&&!empty($r['adapter_installed']); }
}
