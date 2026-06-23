<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class AiProviderService
{
    /**
     * Generate article content using an OpenAI-compatible AI provider.
     *
     * @return array{
     *     title: string,
     *     content: string,
     *     excerpt: string,
     *     tags: array<int, string>,
     *     image_keyword: string
     * }
     */
    public function article_generator(string $topic, bool $stream = false): array
    {
        $response = Http::baseUrl((string) config('services.ai_provider.url'))
            ->acceptJson()
            ->withToken((string) config('services.ai_provider.key'))
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(2, 1000)
            ->post('/chat/completions', [
                'model' => (string) config('services.ai_provider.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Anda adalah seorang blogger profesional, penulis artikel SEO, dan pakar konten digital yang andal.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Buatkan artikel menarik tentang: '.$topic,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'article_generator',
                        'schema' => $this->articleSchema(),
                    ],
                ],
                'stream' => $stream,
            ])
            ->throw()
            ->json();

        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('AI provider did not return article content.');
        }

        try {
            /** @var array{
             *     title: string,
             *     content: string,
             *     excerpt: string,
             *     tags: array<int, string>,
             *     image_keyword: string
             * } $article
             */
            $article = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('AI provider returned an invalid article payload.', previous: $exception);
        }

        return $article;
    }

    /**
     * @return array{
     *     type: string,
     *     additionalProperties: bool,
     *     required: array<int, string>,
     *     properties: array<string, array<string, mixed>>
     * }
     */
    private function articleSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'title',
                'content',
                'excerpt',
                'tags',
                'image_keyword',
            ],
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Judul artikel yang menarik, informatif, dan ramah SEO. Maksimal 80 karakter.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Isi artikel lengkap dan mendalam (minimal 4 paragraf). Gunakan format tag HTML dasar seperti <p>, <h3>, dan <strong> untuk struktur teksnya.',
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'Ringkasan singkat artikel dalam 2-3 kalimat untuk deskripsi meta atau cuplikan halaman depan. Maksimal 160 karakter.',
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => 'Daftar kata kunci atau tag yang relevan dengan isi artikel. Maksimal 5 kata kunci pendek.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'image_keyword' => [
                    'type' => 'string',
                    'description' => 'Keyword untuk gambar artikel. Maksimal 1 kata kunci pendek dalam bahasa Inggris.',
                ],
            ],
        ];
    }
}
