<?php

// Include Google Cloud dependencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\AccessToken;

if (!class_exists('copy_recording_processor')) {
    /**
     * Processing incoming pubsub subscription to fusion.cdr.create and (if the recording is available on this machine)
     * copies the recording to the bucket defined for the recordings
     */
    class copy_recording_processor
    {

        public function process($data)
        {
            $this->log("Processing message: " . print_r($data, true));
            $payload = $this->verifyToken();
            $this->log("Logged in as: " . $payload["email"]);
        }

        private function verifyToken()
        {
            // Remove the `Bearer ` prefix from the token.
            // If using another request manager such as Symfony HttpFoundation,
            // use `Authorization` as the header name, e.g. `$request->headers->get('Authorization')`.
            $jwt = explode(' ', $_SERVER['HTTP_AUTHORIZATION'])[1];

            // Using the Access Token utility requires installation of the `phpseclib/phpseclib` dependency at version 2.
            $accessTokenUtility = new AccessToken();
            $payload = $accessTokenUtility->verify($jwt);
            if (!$payload) {
                throw new \RuntimeException('Invalid token');
            }
            return $payload;
        }

        /**
         * cdr process logging
         * @param $message string to log
         */
        private function log(string $message)
        {
            //save to file system (alternative to a syslog server)
            $fp = fopen($_SESSION['server']['temp']['dir'] . '/copy_recording_processor.log', 'a+');
            if (!$fp) {
                return;
            }
            fwrite($fp, $message);
            fclose($fp);
        }
    } // end class: copy_recording_processor

}
