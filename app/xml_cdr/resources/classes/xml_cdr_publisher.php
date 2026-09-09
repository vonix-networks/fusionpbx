<?php
/*
	Vonix additions to FusionPBX

	Publishes call detail records to a Google Cloud Pub/Sub topic as they are
	written to the database, so downstream services can consume call activity
	without polling the CDR tables.
*/

if (!class_exists('xml_cdr_publisher')) {

	class xml_cdr_publisher {

		/**
		 * Topic used when no topic has been configured.
		 */
		const DEFAULT_TOPIC = 'fusion.cdr.create';

		/**
		 * @var settings
		 */
		private $settings;

		/**
		 * @var \Google\Cloud\PubSub\Topic
		 */
		private $topic;

		public function __construct($settings = null) {
			require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
			$this->settings = $settings ?? new settings();
		}

		/**
		 * Publishes a single call detail record.
		 *
		 * @param array $cdr the record as it was written to the database
		 * @return bool true when the message was accepted by pub/sub
		 */
		public function publish(array $cdr): bool {
			try {
				$this->topic()->publish(['data' => json_encode($cdr)]);
				return true;
			}
			catch (Throwable $t) {
				$this->log("publish failed: ".$t->getMessage());
				return false;
			}
		}

		/**
		 * Publishes every call detail record in an xml_cdr style array. The
		 * array is keyed by table name, so only the xml_cdr rows are sent.
		 *
		 * @param array $array the array handed to database::save()
		 * @return int number of records published
		 */
		public function publish_array(array $array): int {
			$published = 0;
			foreach ($array['xml_cdr'] ?? [] as $row) {
				if (is_array($row) && $this->publish($row)) {
					$published++;
				}
			}
			return $published;
		}

		/**
		 * True when a project has been configured, so callers can skip the
		 * work entirely on systems that do not publish.
		 */
		public function enabled(): bool {
			return !empty($this->settings->get('server', 'project'));
		}

		private function topic() {
			if (!isset($this->topic)) {
				$project = $this->settings->get('server', 'project');
				if (empty($project)) {
					throw new RuntimeException("google cloud project is not configured, set a text value at server / project");
				}
				$name = $this->settings->get('cdr', 'pubsub_topic') ?: self::DEFAULT_TOPIC;
				//rest keeps this working without the grpc extension
				$client = new \Google\Cloud\PubSub\PubSubClient([
					'projectId' => $project,
					'transport' => 'rest',
				]);
				$this->topic = $client->topic($name);
			}
			return $this->topic;
		}

		private function log(string $message): void {
			error_log('[xml_cdr_publisher] '.$message);
		}

	} //class

}
