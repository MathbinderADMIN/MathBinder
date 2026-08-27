<?php
if (!defined('ABSPATH')) exit;

/** Minimal RS256/JWT utilities for the LTI 1.3 trust boundary. */
final class MathBinder_Canvas_Crypto {
    public static function b64url_encode($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    public static function b64url_decode($value) {
        $value = strtr((string)$value, '-_', '+/');
        return base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    public static function decode($jwt) {
        $parts = explode('.', (string)$jwt);
        if (count($parts) !== 3) return new WP_Error('mb_lti_jwt_format', 'The LTI token is malformed.');
        $header = json_decode(self::b64url_decode($parts[0]), true);
        $claims = json_decode(self::b64url_decode($parts[1]), true);
        if (!is_array($header) || !is_array($claims)) return new WP_Error('mb_lti_jwt_json', 'The LTI token cannot be decoded.');
        return ['header'=>$header,'claims'=>$claims,'signed'=>$parts[0].'.'.$parts[1],'signature'=>self::b64url_decode($parts[2])];
    }

    public static function verify_canvas_jwt($jwt, array $settings, $expected_nonce = '') {
        $decoded = self::decode($jwt);
        if (is_wp_error($decoded)) return $decoded;
        if (($decoded['header']['alg'] ?? '') !== 'RS256' || empty($decoded['header']['kid'])) return new WP_Error('mb_lti_alg', 'Canvas must sign LTI launches with RS256 and a key ID.');
        $jwks = self::fetch_jwks($settings['canvas_jwks_url']);
        if (is_wp_error($jwks)) return $jwks;
        $pem = self::jwk_to_pem(self::find_jwk($jwks, $decoded['header']['kid']));
        if (is_wp_error($pem)) return $pem;
        if (openssl_verify($decoded['signed'], $decoded['signature'], $pem, OPENSSL_ALGO_SHA256) !== 1) return new WP_Error('mb_lti_signature', 'Canvas launch signature verification failed.');
        $c = $decoded['claims']; $now = time();
        if (($c['iss'] ?? '') !== rtrim($settings['canvas_url'], '/')) return new WP_Error('mb_lti_issuer', 'Canvas issuer does not match this deployment.');
        $aud = (array)($c['aud'] ?? []);
        if (!in_array((string)$settings['client_id'], array_map('strval', $aud), true)) return new WP_Error('mb_lti_audience', 'LTI client ID does not match.');
        if (empty($c['exp']) || (int)$c['exp'] < $now - 30 || (!empty($c['iat']) && (int)$c['iat'] > $now + 60)) return new WP_Error('mb_lti_time', 'The Canvas launch token has expired or is not yet valid.');
        if ($expected_nonce !== '' && !hash_equals($expected_nonce, (string)($c['nonce'] ?? ''))) return new WP_Error('mb_lti_nonce', 'The Canvas launch nonce does not match.');
        $deployment = $c['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? '';
        if (!hash_equals((string)$settings['deployment_id'], (string)$deployment)) return new WP_Error('mb_lti_deployment', 'Canvas deployment ID does not match.');
        return $c;
    }

    public static function sign(array $claims, array $settings) {
        $jwk = json_decode((string)$settings['public_jwk'], true);
        $header = ['typ'=>'JWT','alg'=>'RS256','kid'=>(string)($jwk['kid'] ?? 'mathbinder')];
        $signed = self::b64url_encode(wp_json_encode($header)).'.'.self::b64url_encode(wp_json_encode($claims));
        $signature = '';
        if (!openssl_sign($signed, $signature, $settings['private_key'], OPENSSL_ALGO_SHA256)) return new WP_Error('mb_lti_sign', 'MathBinder could not sign the LTI message.');
        return $signed.'.'.self::b64url_encode($signature);
    }

    private static function fetch_jwks($url) {
        $cache = get_transient('mb_canvas_jwks_'.md5($url));
        if (is_array($cache)) return $cache;
        $response = wp_safe_remote_get($url, ['timeout'=>15,'redirection'=>2]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return new WP_Error('mb_lti_jwks', 'Canvas signing keys could not be retrieved.');
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['keys'])) return new WP_Error('mb_lti_jwks_format', 'Canvas returned invalid signing keys.');
        set_transient('mb_canvas_jwks_'.md5($url), $body, HOUR_IN_SECONDS); return $body;
    }
    private static function find_jwk(array $jwks, $kid) { foreach ($jwks['keys'] as $key) if (($key['kid'] ?? '') === $kid) return $key; return []; }
    private static function jwk_to_pem(array $jwk) {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) return new WP_Error('mb_lti_key', 'Canvas signing key is unavailable.');
        $n=self::b64url_decode($jwk['n']); $e=self::b64url_decode($jwk['e']);
        $seq=self::der(0x02, (ord($n[0])>127?"\0":'').$n).self::der(0x02,(ord($e[0])>127?"\0":'').$e);
        $rsa=self::der(0x30,$seq); $alg=hex2bin('300d06092a864886f70d0101010500'); $bit=self::der(0x03,"\0".$rsa);
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(self::der(0x30,$alg.$bit)),64,"\n")."-----END PUBLIC KEY-----\n";
    }
    private static function der($tag,$value) { $len=strlen($value); if($len<128)$l=chr($len); else{$b=ltrim(pack('N',$len),"\0");$l=chr(0x80|strlen($b)).$b;} return chr($tag).$l.$value; }
}
