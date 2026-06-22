<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class ArticleGenerator implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Anda adalah seorang blogger profesional, penulis artikel SEO, dan pakar konten digital yang andal.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {

        return [
            'title' => $schema->string()
                ->description('Judul artikel yang menarik, informatif, dan ramah SEO. Maksimal 80 karakter.')
                ->required(),

            'content' => $schema->string()
                ->description('Isi artikel lengkap dan mendalam (minimal 4 paragraf). Gunakan format tag HTML dasar seperti <p>, <h3>, dan <strong> untuk struktur teksnya.')
                ->required(),

            'excerpt' => $schema->string()
                ->description('Ringkasan singkat artikel dalam 2-3 kalimat untuk deskripsi meta atau cuplikan halaman depan. Maksimal 160 karakter.')
                ->required(),

            'tags' => $schema->array()
                ->items($schema->string()) // Memastikan isi array adalah string data
                ->description('Daftar kata kunci atau tag yang relevan dengan isi artikel. Maksimal 5 kata kunci pendek.')
                ->required(),

            'image_keyword' => $schema->string()
                ->description('Keyword untuk gambar artikel. Maksimal 1 kata kunci pendek dalam bahasa Inggris.')
                ->required(),
        ];
    }
}
