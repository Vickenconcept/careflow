<?php

namespace App\Domains\Communications;

use App\Models\MessageTemplate;

class MessageTemplateRenderer
{
    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string|null, body: string, language: string}
     */
    public function render(MessageTemplate $template, string $language, array $variables = []): array
    {
        $language = strtolower($language);
        $subjects = $template->subject_translations ?? [];
        $bodies = $template->body_translations ?? [];

        $subject = $this->pickTranslation($subjects, $language);
        $body = $this->pickTranslation($bodies, $language) ?? '';

        return [
            'subject' => $subject !== null ? $this->replace($subject, $variables) : null,
            'body' => $this->replace($body, $variables),
            'language' => array_key_exists($language, $bodies) || array_key_exists($language, $subjects)
                ? $language
                : 'en',
        ];
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    protected function pickTranslation(array $translations, string $language): ?string
    {
        $value = $translations[$language] ?? $translations['en'] ?? reset($translations);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    protected function replace(string $text, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr($text, $replacements);
    }
}
