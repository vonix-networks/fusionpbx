<?php
/*
	Vonix additions to FusionPBX

	Uploads local media (call recordings, voicemail messages) to a Google
	Cloud Storage bucket. Credentials come from the environment the same way
	every other google-cloud client resolves them, normally the
	GOOGLE_APPLICATION_CREDENTIALS variable or the instance metadata server.
*/

if (!class_exists('cloud_storage')) {

	class cloud_storage {

		/**
		 * @var settings
		 */
		private $settings;

		/**
		 * @var \Google\Cloud\Storage\StorageClient
		 */
		private $client;

		public function __construct($settings = null) {
			require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
			$this->settings = $settings ?? new settings();
		}

		/**
		 * The google cloud project the buckets live in.
		 *
		 * @return string
		 * @throws RuntimeException when the project has not been configured
		 */
		public function project(): string {
			$project = $this->settings->get('server', 'project');
			if (empty($project)) {
				throw new RuntimeException("google cloud project is not configured, set a text value at server / project");
			}
			return $project;
		}

		/**
		 * Uploads a local file to a bucket.
		 *
		 * @param string $source local path of the file to upload
		 * @param string $bucket destination bucket name
		 * @param string $name object name to write within the bucket
		 * @return bool true when the object was written
		 */
		public function upload(string $source, string $bucket, string $name): bool {
			if (!is_readable($source)) {
				$this->log("source is missing or unreadable: ".$source);
				return false;
			}

			$handle = fopen($source, 'r');
			if ($handle === false) {
				$this->log("could not open source: ".$source);
				return false;
			}

			try {
				$this->client()->bucket($bucket)->upload($handle, ['name' => $name]);
				return true;
			}
			catch (Throwable $t) {
				$this->log("upload of ".$source." to ".$bucket."/".$name." failed: ".$t->getMessage());
				return false;
			}
			finally {
				if (is_resource($handle)) {
					fclose($handle);
				}
			}
		}

		/**
		 * Strips the configured recordings directory off an absolute path so
		 * the object keeps a tidy relative name inside the bucket.
		 *
		 * @param string $path absolute path of a recording
		 * @return string path relative to the recordings directory
		 */
		public function relative_recording_path(string $path): string {
			$base = $this->settings->get('switch', 'recordings');
			if (!empty($base) && str_starts_with($path, $base)) {
				$path = substr($path, strlen($base));
			}
			return ltrim($path, '/');
		}

		private function client() {
			if (!isset($this->client)) {
				//rest keeps this working without the grpc extension
				$this->client = new \Google\Cloud\Storage\StorageClient([
					'projectId' => $this->project(),
					'transport' => 'rest',
				]);
			}
			return $this->client;
		}

		private function log(string $message): void {
			error_log('[cloud_storage] '.$message);
		}

	} //class

}
