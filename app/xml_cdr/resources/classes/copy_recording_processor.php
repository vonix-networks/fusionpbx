<?php

// Include Google Cloud dependencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\AccessToken;
use Google\Cloud\PubSub\IncomingMessageTrait;
use Google\Cloud\Storage\StorageClient;

class AuthenticationException extends \RuntimeException implements \http\Exception {
}

if (!class_exists('copy_recording_processor')) {

    /**
     * Processing incoming pubsub subscription to fusion.cdr.create and (if the recording is available on this machine)
     * copies the recording to the bucket defined for the recordings
     */
    class copy_recording_processor
    {
        use IncomingMessageTrait;

        public function process($data)
        {
            $project = $_SESSION['server']['project']['text'];
            if (!$project) {
                throw new \http\Exception\InvalidArgumentException("Project not configured for recording copy, set a text value at server / project with the gcp project to publish to.");
            }

            $bucket_name = $_SESSION['recordings']['bucket']['text'];
            if (!$bucket_name) {
                throw new \http\Exception\InvalidArgumentException("Recording bucket not configured for recording copy, set a text value at recordings / bucket with the gcs bucket to copy to.");
            }

            $payload = $this->verifyToken();
            $this->log("Logged in as: " . $payload["email"]);


            $message = $this->messageFactory(json_decode($data, true), null, $project, true);

            $record_path = $message["message"]["data"]["record_path"];
            $record_name = $message["message"]["data"]["record_name"];

            if ($record_path && $record_name) {
                $path = $record_path . "/" . $record_name;
                $this->copy($path, $bucket_name, $path);
            }
        }

        private function copy($src, $bucket_name, $dest) {
            try {
                $bucket = (new StorageClient())
                    ->bucket($bucket_name);

                $bucket->upload(fopen($src, 'r'), ['name' => $dest]);
            } catch (Exception $e) {
                $this->log("Error during copy: " . $e->getMessage());
            }
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
                throw new AuthenticationException('Invalid token');
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
            fwrite($fp, "[ " . date("Y-m-d H:i:s") . "] " . $message . "\n");
            fclose($fp);
        }
    } // end class: copy_recording_processor

}
