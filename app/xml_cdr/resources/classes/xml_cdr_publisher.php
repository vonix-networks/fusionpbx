<?php

// Include Google Cloud dependencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\PubSub\PubSubClient;

if (!class_exists('xml_cdr_publisher')) {
    class xml_cdr_publisher
    {
        const TOPIC = "fusion.cdr.create";

        public function publish($cdr)
        {
            try {
                $project = $_SESSION['server']['project']['text'];
                if (!$project) {
                    throw new Exception("Project not configured for cdr publishing, set a text value at server / project with the gcp project to publish to.");
                }

                $pubsub = new PubSubClient([
                    'projectId' => $project,
                ]);

                $topic = $pubsub->topic(xml_cdr_publisher::TOPIC);
                $topic->publish(['data' => json_encode($cdr)]);
            } catch (Exception $e) {
                $this->log("Error during publish: " . $e->getMessage());
            }
        }

        /**
         * cdr process logging
         * @param $message string to log
         */
        private function log(string $message)
        {
            //save to file system (alternative to a syslog server)
            $fp = fopen($_SESSION['server']['temp']['dir'] . '/xml_cdr_publisher.log', 'a+');
            if (!$fp) {
                return;
            }
            fwrite($fp, $message);
            fclose($fp);
        }

    }
}