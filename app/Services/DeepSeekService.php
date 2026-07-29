<?php

namespace App\Services;

/**
 * DeepSeekService — Integração com a API do DeepSeek para extração de conhecimento histórico
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
     * Analisa o texto de um documento histórico e extrai entidades e relações em formato estruturado.
     *
     * @param string $docTitle Título do documento
     * @param string $docText Conteúdo/transcrição do documento
     * @param array $extraAttributes Atributos bibliográficos do documento
     * @return array Array estruturado com ['entities' => [...], 'relationships' => [...]]
     */
    public function extractKnowledge(string $docTitle, string $docText, array $extraAttributes = []): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('A chave DEEPSEEK_API_KEY não está configurada no arquivo .env.');
        }

        $systemPrompt = <<<PROMPT
Você é um especialista em análise documental histórica e extração de redes e grafos de conhecimento, com foco na história do Brasil nas décadas de 1920 e 1930 (período de conflitos políticos, repressão e fragilização democrática).

Sua tarefa é analisar o texto do documento fornecido e extrair:
1. ENTIDADES mencionadas ou implicitamente presentes:
   - "person": Pessoas (ex: indiciados, delegados, testemunhas, militantes, autoridades). Atributos possíveis: ocupacao, apelido, cargo, filiacao.
   - "location": Locais (ex: cidades, prisões, quarteis, ruas, órgãos públicos). Atributos possíveis: municipio, estado, tipo_local.
   - "event": Eventos datados ou marcantes (ex: prisões, depoimentos, julgamentos, manifestações, confrontos). Atributos possíveis: data, tipo_evento, descricao.

2. RELAÇÕES entre essas entidades:
   - relationship_type: Nome da relação em snake_case (ex: participou_de, foi_preso_em, investigou, denunciou, liderou, presente_em, associado_a, submetido_a).
   - source_name: Nome exato da entidade origem (como extraída no array de entidades).
   - target_name: Nome exato da entidade destino (como extraída no array de entidades).
   - direction: "directed" ou "symmetric".
   - confidence: Valor decimal entre 0.50 e 0.99 indicando o grau de certeza histórica da inferência a partir do texto.
   - excerpt: Trecho curto do texto original de onde a relação foi extraída (máximo 150 caracteres).

REGRAS OBRIGATÓRIAS:
- Retorne EXCLUSIVAMENTE um objeto JSON válido, sem trechos em markdown ```json ... ``` ou texto explicativo extra.
- Estrutura de saída esperada:
{
  "entities": [
    {"name": "Nome da Entidade", "type": "person|location|event", "attributes": {"chave": "valor"}}
  ],
  "relationships": [
    {
      "source_name": "Nome Origem",
      "target_name": "Nome Destino",
      "relationship_type": "participou_de",
      "direction": "directed",
      "confidence": 0.85,
      "excerpt": "trecho do documento..."
    }
  ]
}
PROMPT;

        $userPrompt = "Título do Documento: {$docTitle}\n\n";
        if (!empty($extraAttributes)) {
            $userPrompt .= "Metadados: " . json_encode($extraAttributes, JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        $userPrompt .= "Conteúdo do Documento:\n" . $docText;

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Erro na chamada da API DeepSeek: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('A API DeepSeek retornou o status HTTP ' . $httpCode . ': ' . $response);
        }

        $json = json_decode($response, true);
        $content = $json['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('A resposta da API DeepSeek veio vazia.');
        }

        // Limpar possíveis blocos de markdown se retornados
        $cleanedContent = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        $extractedData  = json_decode($cleanedContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Falha ao decodificar JSON retornado pela IA: ' . json_last_error_msg());
        }

        return [
            'entities'      => $extractedData['entities'] ?? [],
            'relationships' => $extractedData['relationships'] ?? [],
        ];
    }
}
