<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    final class ErrorEnvelope
    {
        public const VERSION = '0.1';



        /**

         * Build a standardized success envelope.

         * @param mixed $id

         * @param array<string,mixed> $payload

         * @param array<string,mixed> $meta

         */

        public static function success($id, array $payload, array $meta = []): array
        {
            $response = [
                'id' => $id,
                'version' => self::VERSION,
                'result' => $payload,
            ];

            if ($meta !== []) {
                $response['meta'] = $meta;
            }

            return $response;
        }

        /**
         * Build a standardized error envelope.
         * @param mixed $id
         * @param int $code
         * @param string $message
         * @param array<string,mixed>|null $details
         * @param array<string,mixed> $meta
         */
        public static function error($id, int $code, string $message, ?array $details = null, array $meta = []): array
        {
            $err = [ 'code' => $code, 'message' => $message ];

            if ($details !== null) {
                $err['details'] = self::redact($details);
            }

            $response = [
                'id' => $id,
                'version' => self::VERSION,
                'error' => $err,
            ];

            if ($meta !== []) {
                $response['meta'] = $meta;
            }

            return $response;
        }



        /** Redact sensitive keys recursively. */

        public static function redact(array $data): array
        {

            $sensitive = ['password','token','secret','apiKey','authorization','auth','bearer'];

            $out = [];

            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $out[$k] = self::redact($v);
                } else {
                    $lower = strtolower((string)$k);

                    if (in_array($lower, $sensitive, true)) {
                        $out[$k] = '***';
                    } else {
                        $out[$k] = $v;
                    }
                }
            }

            return $out;
        }
    }
