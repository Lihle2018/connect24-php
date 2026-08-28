<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/** `$client->templates` — stored bodies, so copy lives on the platform rather than in your deploy. */
final class Templates
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(int $limit = 100): array
    {
        return $this->transport->get('v1/templates?limit=' . $limit) ?? [];
    }

    /**
     * Creates a template.
     *
     * Placeholders are written `{{name}}` and filled at send time from the variables you pass. A
     * placeholder with no matching variable is left as-is rather than blanked, so a missing value
     * shows up in a test message instead of silently sending an empty sentence.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        ?string $subject = null,
        ?string $html = null,
        ?string $text = null
    ): array {
        return $this->transport->post('v1/templates', [
            'name' => $name,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ]) ?? [];
    }

    /**
     * Updates a template, which bumps its version.
     *
     * The version is why editing is safe: a message already sent stays traceable to the body that
     * produced it, rather than appearing to have said whatever the template says today.
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $subject = null,
        ?string $html = null,
        ?string $text = null
    ): array {
        return $this->transport->put('v1/templates/' . rawurlencode($id), [
            'name' => $name,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ]) ?? [];
    }

    public function delete(string $id): void
    {
        $this->transport->delete('v1/templates/' . rawurlencode($id));
    }
}
