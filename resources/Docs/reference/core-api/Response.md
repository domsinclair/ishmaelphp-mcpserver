# Response

- FQCN: `Ishmael\Core\Http\Response`
- Type: class

## Public Methods

- `text(string $body, int $status, array $headers)`
- `json(mixed $data, int $status, array $headers)`
- `html(string $body, int $status, array $headers)`
- `redirect(string $location, int $status, array $headers)`
- `fromThrowable(Throwable $e, bool $debug)`
- `setStatusCode(int $code)`
- `getStatusCode()`
- `header(string $name, string $value)`
- `withEtag(string $etag, bool $weak)`
- `withLastModified(DateTimeInterface $dt)`
- `getHeaders()`
- `getLastHeaders()`
- `setBody(string $body)`
- `getBody()`
- `refreshLastHeadersSnapshot()`
