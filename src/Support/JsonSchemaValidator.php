<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Support;

    /**

     * Minimal JSON Schema validator (subset) for runtime validation.

     * Supported keywords: type, required, properties, additionalProperties,

     * items (schema), enum, minimum, maximum, minLength, maxLength.

     * Returns a flat list of error objects: [ { path, message } ].

     */

    final class JsonSchemaValidator
    {
        /**

         * @param mixed $data

         * @param array<string,mixed> $schema

         * @return array<int,array{path:string,message:string}>

         */

        public function validate($data, array $schema, string $path = ''): array
        {

            $errors = [];



            // type

            if (isset($schema['type'])) {
                $type = $schema['type'];

                $ok = $this->checkType($data, $type);

                if (!$ok) {
                    $errors[] = [

                        'path' => $path,

                        'message' => "Expected type {$type}",

                    ];

                    // If type mismatches, skip deeper checks to avoid noise

                    return $errors;
                }
            }



            // enum

            if (isset($schema['enum']) && is_array($schema['enum'])) {
                $found = false;

                foreach ($schema['enum'] as $v) {
                    if ($data === $v) {
                        $found = true;

                        break;
                    }
                }

                if (!$found) {
                    $errors[] = [ 'path' => $path, 'message' => 'Value not in enum' ];
                }
            }



            // numeric constraints

            if (is_int($data) || is_float($data)) {
                if (isset($schema['minimum']) && $data < $schema['minimum']) {
                    $errors[] = [ 'path' => $path, 'message' => 'Value below minimum' ];
                }

                if (isset($schema['maximum']) && $data > $schema['maximum']) {
                    $errors[] = [ 'path' => $path, 'message' => 'Value above maximum' ];
                }
            }



            // string constraints

            if (is_string($data)) {
                if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
                    $errors[] = [ 'path' => $path, 'message' => 'String shorter than minLength' ];
                }

                if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
                    $errors[] = [ 'path' => $path, 'message' => 'String longer than maxLength' ];
                }
            }



            // arrays

            if (is_array($data) && ($schema['type'] ?? null) === 'array') {
                $itemSchema = $schema['items'] ?? null;

                if (is_array($itemSchema)) {
                    foreach (array_values($data) as $i => $val) {
                        $errors = array_merge($errors, $this->validate($val, $itemSchema, $path . '[' . $i . ']'));
                    }
                }
            }



            // objects

            if (is_array($data) && ($schema['type'] ?? null) === 'object') {
                $props = $schema['properties'] ?? [];

                $required = $schema['required'] ?? [];

                // required

                if (is_array($required)) {
                    foreach ($required as $key) {
                        if (!array_key_exists($key, $data)) {
                            $errors[] = [ 'path' => $path, 'message' => "Missing required property: {$key}" ];
                        }
                    }
                }

                // properties

                if (is_array($props)) {
                    foreach ($props as $key => $propSchema) {
                        if (array_key_exists($key, $data) && is_array($propSchema)) {
                            $errors = array_merge(
                                $errors,
                                $this->validate($data[$key], $propSchema, $path === '' ? $key : $path . '.' . $key)
                            );
                        }
                    }
                }

                // additionalProperties

                if (($schema['additionalProperties'] ?? true) === false) {
                    $allowed = is_array($props) ? array_keys($props) : [];

                    foreach ($data as $k => $_) {
                        if (!in_array($k, $allowed, true)) {
                            $errors[] = [ 'path' => $path === '' ? $k : $path . '.' . $k, 'message' => 'Additional property not allowed' ];
                        }
                    }
                }
            }



            return $errors;
        }



        /**
         * @param mixed $data
         * @param string|array<int, string> $type
         */
        private function checkType($data, $type): bool
        {
            switch ($type) {
                case 'object':
                    // Treat empty PHP arrays as valid JSON objects as well, because

                    // callers often pass [] for an empty params object in RPC calls.

                    // Consider associative arrays OR empty array as an object.

                    return is_array($data) && (array_values($data) !== $data || $data === []);

                case 'array':
                    return is_array($data);

                case 'string':
                    return is_string($data);

                case 'integer':
                    return is_int($data);

                case 'number':
                    return is_int($data) || is_float($data);

                case 'boolean':
                    return is_bool($data);

                case 'null':
                    return $data === null;

                default:
                    return true; // unknown type: don't block
            }
        }
    }
