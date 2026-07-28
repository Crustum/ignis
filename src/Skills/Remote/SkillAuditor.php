<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

use Cake\Core\Configure;
use Cake\Http\Client;
use Throwable;

/**
 * Retrieves independent security audit results for remote skills.
 */
class SkillAuditor
{
    /**
     * Create a skill auditor.
     *
     * @param \Cake\Http\Client|null $httpClient Optional HTTP client for testing
     * @param string|null $auditEndpoint Optional audit endpoint override (tests / DI)
     */
    public function __construct(
        protected ?Client $httpClient = null,
        protected ?string $auditEndpoint = null,
    ) {
    }

    /**
     * HTTP request timeout in seconds.
     *
     * @var int
     */
    protected int $timeoutSeconds = 3;

    /**
     * Whether a hosted audit endpoint is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->auditUrl() !== null;
    }

    /**
     * Audit skills from a common source.
     *
     * @param string $source Skill source
     * @param array<int, string> $skillSlugs Skill slugs to audit
     * @return array<string, array<int, \Crustum\Ignis\Skills\Remote\AuditResult>>
     */
    public function audit(string $source, array $skillSlugs): array
    {
        $url = $this->auditUrl();

        if ($url === null) {
            return [];
        }

        try {
            $response = $this->client()->get($url, [
                'source' => $source,
                'skills' => implode(',', $skillSlugs),
            ], [
                'timeout' => $this->timeoutSeconds,
            ]);

            if ($response->getStatusCode() >= 400) {
                return [];
            }

            $data = $response->getJson();

            return is_array($data) ? $this->parseResponse($data) : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Parse the hosted audit response.
     *
     * @param array<string, mixed> $data Hosted audit response
     * @return array<string, array<int, \Crustum\Ignis\Skills\Remote\AuditResult>>
     */
    protected function parseResponse(array $data): array
    {
        $results = [];

        foreach ($data as $skill => $partners) {
            if (!is_array($partners)) {
                continue;
            }

            $skillResults = [];

            foreach ($partners as $partner => $audit) {
                if (!is_array($audit)) {
                    continue;
                }

                $risk = Risk::tryFrom((string)($audit['risk'] ?? ''));

                if ($risk === null) {
                    continue;
                }

                $skillResults[] = new AuditResult(
                    (string)$partner,
                    $risk,
                    isset($audit['alerts']) ? (int)$audit['alerts'] : null,
                    isset($audit['analyzedAt']) ? (string)$audit['analyzedAt'] : null,
                );
            }

            if ($skillResults !== []) {
                $results[(string)$skill] = $skillResults;
            }
        }

        return $results;
    }

    /**
     * Create the hosted audit HTTP client.
     *
     * @return \Cake\Http\Client HTTP client
     */
    protected function client(): Client
    {
        if ($this->httpClient instanceof Client) {
            return $this->httpClient;
        }

        return new Client([
            'timeout' => $this->timeoutSeconds,
        ]);
    }

    /**
     * Get the configured audit endpoint, or null when unset.
     *
     * @return string|null Audit endpoint URL
     */
    protected function auditUrl(): ?string
    {
        if (is_string($this->auditEndpoint) && $this->auditEndpoint !== '') {
            return $this->auditEndpoint;
        }

        $url = Configure::read('Ignis.hosted.audit_url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
