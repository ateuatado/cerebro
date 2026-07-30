<?php

namespace App\Services;

/**
 * DeepSeekService — Integração com a API do DeepSeek para extração de conhecimento histórico
 * Suporta análise de imagens (manuscritos) via DeepSeek Vision (deepseek-chat com image_url)
 */
class DeepSeekService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.deepseek.com/chat/completions';
    private string $model  = 'deepseek-chat';

    public function __construct()
    {
        $this->apiKey = getenv('DEEPSEEK_API_KEY') ?: '';

        $envPath = defined('ROOTPATH') ? ROOTPATH . '.env' : __DIR__ . '/../../.env';
        if (empty($this->apiKey) && file_exists($envPath)) {
            $lines = file($envPath);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'DEEPSEEK_API_KEY') === 0) {
                    $parts = explode('=', $line, 2);
                    $this->apiKey = trim($parts[1] ?? '');
                    break;
                }
            }
        }
    }

    /**
     * Envia requisição para a API DeepSeek
     */
    private function callApi(array $messages): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('A chave DEEPSEEK_API_KEY não está configurada no arquivo .env.');
        }

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = \curl_init($this->apiUrl);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($data, \JSON_UNESCAPED_UNICODE));
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 90);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error    = \curl_error($ch);
        \curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Erro na chamada da API DeepSeek: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('A API DeepSeek retornou status ' . $httpCode . ': ' . \mb_substr($response, 0, 500));
        }

        $json = \json_decode($response, true);
        $content = $json['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('A resposta da API DeepSeek veio vazia.');
        }

        $cleanedContent = \preg_replace('/^```json\s*|\s*```$/i', '', \trim($content));
        $extractedData  = \json_decode($cleanedContent, true);

        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new \RuntimeException('Falha ao decodificar JSON retornado pela IA: ' . \json_last_error_msg());
        }

        return $extractedData ?: [];
    }

    /**
     * Lê uma imagem de recorte (Base64) diretamente via DeepSeek Vision
     * e extrai entidades e relações do manuscrito.
     * 
     * @param string $docTitle   Título do documento
     * @param string $imageBase64 Dados da imagem em Base64 (com prefixo data:image/...)
     * @return array ['transcription', 'entities', 'relationships']
     */
    public function extractFromCropImage(string $docTitle, string $imageBase64): array
    {
        $systemPrompt = <<<PROMPT
Você é um historiador e paleógrafo especialista em leitura de documentos manuscritos cursivos do Brasil das décadas de 1920 e 1930 (boletins de batalhões militares, registros de prisões, jornais e processos judiciais).

A imagem a seguir é um recorte de um documento manuscrito em caligrafia cursiva antiga, com possíveis abreviações da época (ex: "Ten." = Tenente, "Sgt." = Sargento, "Alferes", "2º Batalhão", "Mappa diario", "preso_em").

LEIA DIRETAMENTE A IMAGEM e:
1. "transcription": Faça a transcrição completa e fiel do texto manuscrito visível.
2. "entities": Extraia todas as pessoas (com patentes/cargos em atributos), locais, eventos e organizações.
3. "relationships": Extraia todas as conexões entre essas entidades.

Retorne EXCLUSIVAMENTE um JSON:
{
  "transcription": "Texto completo transcrito do manuscrito na imagem...",
  "entities": [
    {"name": "Nome", "type": "person|location|event", "attributes": {"cargo": "..."}}
  ],
  "relationships": [
    {
      "source_name": "Nome Origem",
      "target_name": "Nome Destino",
      "relationship_type": "lotado_em",
      "direction": "directed",
      "confidence": 0.90,
      "excerpt": "trecho..."
    }
  ]
}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Documento: {$docTitle}\n\nTranscreva e extraia entidades/relações deste recorte de manuscrito histórico."
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $imageBase64
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->callApi($messages);

        return [
            'transcription' => $result['transcription'] ?? '',
            'entities'      => $result['entities'] ?? [],
            'relationships' => $result['relationships'] ?? [],
        ];
    }

    /**
     * Transcreve e extrai entidades/relações a partir do texto bruto de um recorte de imagem manuscrita.
     */
    public function extractFromCropText(string $docTitle, string $rawOcrText): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('A chave DEEPSEEK_API_KEY não está configurada no arquivo .env.');
        }

        $systemPrompt = <<<PROMPT
Você é um historiador e paleógrafo especialista em leitura de documentos manuscritos cursivos do Brasil das décadas de 1920 e 1930 (boletins de batalhões militares, registros de prisões, jornais e processos judiciais).

O texto a seguir veio de um recorte de imagem de manuscrito em caligrafia cursiva antiga e pode conter ruídos ou abreviações da época (ex: "Ten." = Tenente, "Sgt." = Sargento, "Alferes", "2º Batalhão", "Mappa diario", "preso_em").

Sua missão é:
1. "transcription": Corrigir e restaurar o texto em português respeitando a ortografia/conteúdo original do manuscrito.
2. "entities": Extrair todas as pessoas (com patentes/cargos em atributos), locais, eventos e organizações.
3. "relationships": Extrair todas as conexões entre essas entidades (ex: lotado_em, preso_em, comandado_por, discursou_em).

Estrutura JSON esperada:
{
  "transcription": "Texto manuscrito restaurado...",
  "entities": [
    {"name": "Nome", "type": "person|location|event", "attributes": {"cargo": "..."}}
  ],
  "relationships": [
    {
      "source_name": "Nome Origem",
      "target_name": "Nome Destino",
      "relationship_type": "lotado_em",
      "direction": "directed",
      "confidence": 0.90,
      "excerpt": "trecho..."
    }
  ]
}
PROMPT;

        $userPrompt = "Título do Documento: {$docTitle}\n\nTexto Bruto do Recorte:\n{$rawOcrText}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $result = $this->callApi($messages);

        return [
            'transcription' => $result['transcription'] ?? $rawOcrText,
            'entities'      => $result['entities'] ?? [],
            'relationships' => $result['relationships'] ?? [],
        ];
    }

    /**
     * Extração de alta densidade em documentos longos (ex: jornais, processos extensos),
     * dividindo o texto em blocos sequenciais para extrair dezenas/centenas de relações sem truncamento da IA.
     */
    public function extractKnowledgeChunked(string $docTitle, string $docText, array $extraAttributes = [], int $chunkSize = 3000): array
    {
        if (\strlen($docText) <= $chunkSize) {
            return $this->extractKnowledge($docTitle, $docText, $extraAttributes);
        }

        $lines = \explode("\n", $docText);
        $chunks = [];
        $currentChunk = '';

        foreach ($lines as $line) {
            if (\strlen($currentChunk) + \strlen($line) > $chunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = '';
            }
            $currentChunk .= $line . "\n";
        }
        if (!empty(\trim($currentChunk))) {
            $chunks[] = $currentChunk;
        }

        $allEntities     = [];
        $allRelationships = [];

        foreach ($chunks as $index => $chunkText) {
            $chunkTitle = "{$docTitle} (Parte " . ($index + 1) . " de " . \count($chunks) . ")";
            try {
                $res = $this->extractKnowledge($chunkTitle, $chunkText, $extraAttributes);

                foreach ($res['entities'] as $entity) {
                    $name = \trim($entity['name'] ?? '');
                    $type = $entity['type'] ?? 'person';
                    if (!empty($name)) {
                        $key = \mb_strtolower($name) . '|' . $type;
                        if (!isset($allEntities[$key])) {
                            $allEntities[$key] = $entity;
                        } else {
                            $allEntities[$key]['attributes'] = \array_merge(
                                $allEntities[$key]['attributes'] ?? [],
                                $entity['attributes'] ?? []
                            );
                        }
                    }
                }

                foreach ($res['relationships'] as $rel) {
                    $allRelationships[] = $rel;
                }

            } catch (\Exception $e) {
                // Log da falha no bloco e continua nos próximos
            }
        }

        return [
            'entities'      => \array_values($allEntities),
            'relationships' => $allRelationships,
        ];
    }

    /**
     * Analisa o texto de um documento histórico e extrai entidades e relações em formato estruturado.
     */
    public function extractKnowledge(string $docTitle, string $docText, array $extraAttributes = []): array
    {
        $systemPrompt = <<<PROMPT
Você é um historiador especialista em análise exaustiva e de alta densidade de jornais e documentos do Brasil das décadas de 1920 e 1930 (movimento operário, anarquismo, repressão policial, greves, edições de jornais).

Sua missão é LER EXAUSTIVAMENTE o texto e EXTRAIR O MÁXIMO POSSÍVEL de entidades e relações. Seja extremamente detalhista — extraia dezenas de nomes, locais, jornais, organizações, sindicatos, prisioneiros e eventos mencionados no texto!

1. ENTIDADES:
   - "person": Pessoas (ex: militantes, oradores, prisioneiros, policiais, redateis, colaboradores, operários, autoridades).
   - "location": Locais (ex: cidades, ruas, praças, prisões, sedes de sindicatos, redações, auditórios).
   - "event": Eventos (ex: greves, prisões, sessões de leitura, comícios, perseguições, edições, reuniões, conferências).

2. RELAÇÕES entre essas entidades:
   - relationship_type (snake_case): ex: publicou, editou, assinou, denunciou, preso_em, discursou_em, militante_de, participou_de, localizado_em, apoia, reprimiu.
   - source_name: Nome exato da entidade origem.
   - target_name: Nome exato da entidade destino.
   - direction: "directed" ou "symmetric".
   - confidence: Valor decimal entre 0.60 e 0.99.
   - excerpt: Trecho original do texto (máx 150 caracteres).

REGRAS OBRIGATÓRIAS:
- Retorne EXCLUSIVAMENTE um objeto JSON válido.
- Seja exaustivo: não resuma ou pule nomes citados no texto.
- Estrutura JSON esperada:
{
  "entities": [
    {"name": "Nome da Entidade", "type": "person|location|event", "attributes": {"cargo": "...", "ocupacao": "..."}}
  ],
  "relationships": [
    {
      "source_name": "Nome Origem",
      "target_name": "Nome Destino",
      "relationship_type": "discursou_em",
      "direction": "directed",
      "confidence": 0.90,
      "excerpt": "trecho do jornal..."
    }
  ]
}
PROMPT;

        $userPrompt = "Título do Documento: {$docTitle}\n\n";
        if (!empty($extraAttributes)) {
            $userPrompt .= "Metadados: " . \json_encode($extraAttributes, \JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        $userPrompt .= "Conteúdo do Documento:\n" . $docText;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        return $this->callApi($messages);
    }
}
