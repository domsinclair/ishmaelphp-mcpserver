# HttpValidators

- FQCN: `Ishmael\Core\Http\HttpValidators`
- Type: class

## Public Methods

- `makeEtag(string $payload, bool $weak)`
- `normalizeEtag(string $etag)`
- `parseIfNoneMatch(string $header)`
- `isEtagMatch(array $clientEtags, string $currentEtag, bool $allowWeak)`
- `formatHttpDate(DateTimeInterface $dt)`
- `parseHttpDate(string $date)`
