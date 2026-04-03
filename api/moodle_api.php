<?php
class MoodleAPI {
    private $base_url;
    private $token;

    public function __construct($moodle_url, $token) {
        $this->base_url = rtrim($moodle_url, '/') . '/webservice/rest/server.php';
        $this->token = $token;
    }

    public function call($function, $params = []) {
        $params['wstoken']       = $this->token;
        $params['wsfunction']    = $function;
        $params['moodlewsrestformat'] = 'json';

        $ch = curl_init($this->base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}