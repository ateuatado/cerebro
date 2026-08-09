<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Spec 11 — Vocabulário Controlado de Atributos por Tipo de Entidade
 *
 * Promove a convenção textual do AGENTS.md (seção "Convenção de atributos
 * bibliográficos") a objeto de banco de dados consultável. Estende o vocabulário
 * para os tipos person, location e event além do document já documentado.
 *
 * O vocabulário é usado para:
 *   1. Guiar os prompts da IA (Gemini e DeepSeek) com as chaves preferidas
 *   2. Popular os dropdowns da modal de aprovação de entidades
 *   3. Garantir consistência semântica nas chaves JSONB ao longo do tempo
 */
class CreateEntityAttributeVocabulary extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE entity_attribute_vocabulary (
                id          SERIAL PRIMARY KEY,
                entity_type entity_type NOT NULL,
                key_name    TEXT NOT NULL,
                label       TEXT NOT NULL,
                description TEXT,
                is_active   BOOLEAN NOT NULL DEFAULT true,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT uq_vocab_type_key UNIQUE (entity_type, key_name)
            )
        ");

        $this->db->query("
            CREATE INDEX idx_vocab_type ON entity_attribute_vocabulary (entity_type);
            CREATE INDEX idx_vocab_active ON entity_attribute_vocabulary (entity_type, is_active);
        ");

        // ── Seed: document ──────────────────────────────────────────────────
        // Promovido diretamente das convenções do AGENTS.md
        $documentKeys = [
            ['autor_responsavel',        'Autor / Responsável',           'Autor ou entidade responsável pelo documento'],
            ['titulo',                   'Título Formal',                 'Título formal do documento conforme consta na fonte'],
            ['tipo_documento',           'Tipo do Documento',             'Natureza do documento: processo_judicial, oficio, correspondencia, foto, periodico…'],
            ['instituicao_custodiadora', 'Instituição Custodiadora',      'Instituição que detém a guarda do original'],
            ['localizacao_arquivistica', 'Localização Arquivística',      'Referência física: fundo, caixa, maço'],
            ['data',                     'Data do Documento',             'Data associada ao documento (YYYY-MM-DD, YYYY-MM ou YYYY)'],
            ['data_acesso',              'Data de Acesso',                'Data em que o documento foi consultado/acessado'],
        ];

        foreach ($documentKeys as [$key, $label, $desc]) {
            $this->db->query(
                "INSERT INTO entity_attribute_vocabulary (entity_type, key_name, label, description)
                 VALUES ('document', ?, ?, ?)",
                [$key, $label, $desc]
            );
        }

        // ── Seed: person ────────────────────────────────────────────────────
        // Chaves para o período 1920-1939: militares, operários, políticos, jornalistas
        $personKeys = [
            ['patente',          'Patente / Graduação',          'Patente militar ou graduação hierárquica (ex: Tenente-Coronel, Sargento)'],
            ['cargo',            'Cargo ou Função',              'Cargo civil ou função exercida à época do documento'],
            ['unidade',          'Unidade / Batalhão',           'Unidade militar, batalhão ou destacamento de lotação'],
            ['faccao_politica',  'Filiação / Tendência Política','Partido, corrente ou posicionamento político (ex: tenentismo, PRP, anarquismo)'],
            ['ocupacao',         'Ocupação / Profissão',         'Ofício ou atividade principal (ex: operário, jornalista, advogado)'],
            ['apelido',          'Apelido / Nome de Guerra',     'Alcunha, pseudônimo ou nome pelo qual era conhecido'],
            ['naturalidade',     'Naturalidade',                 'Cidade e estado de nascimento'],
            ['nacionalidade',    'Nacionalidade',                'Nacionalidade quando relevante (ex: estrangeiros anarquistas)'],
            ['data_nascimento',  'Data de Nascimento',           'Data de nascimento (YYYY ou YYYY-MM-DD)'],
        ];

        foreach ($personKeys as [$key, $label, $desc]) {
            $this->db->query(
                "INSERT INTO entity_attribute_vocabulary (entity_type, key_name, label, description)
                 VALUES ('person', ?, ?, ?)",
                [$key, $label, $desc]
            );
        }

        // ── Seed: location ──────────────────────────────────────────────────
        $locationKeys = [
            ['tipo_local',   'Tipo de Local',   'Natureza do lugar (ex: cidade, praca, prisao, sede_sindicato, redacao, quartel)'],
            ['municipio',    'Município',        'Nome do município'],
            ['estado',       'Estado / UF',      'Unidade federativa (ex: SP, RJ, RS)'],
            ['pais',         'País',             'País quando relevante (ex: para locais de exílio)'],
            ['endereco',     'Endereço',         'Logradouro ou referência de localização mais precisa'],
        ];

        foreach ($locationKeys as [$key, $label, $desc]) {
            $this->db->query(
                "INSERT INTO entity_attribute_vocabulary (entity_type, key_name, label, description)
                 VALUES ('location', ?, ?, ?)",
                [$key, $label, $desc]
            );
        }

        // ── Seed: event ─────────────────────────────────────────────────────
        $eventKeys = [
            ['data_evento',  'Data do Evento',   'Data em que o evento ocorreu (YYYY-MM-DD, YYYY-MM ou YYYY)'],
            ['tipo_evento',  'Tipo de Evento',   'Natureza do evento (ex: greve, prisao, comicio, massacre, golpe, publicacao, julgamento)'],
            ['desfecho',     'Desfecho / Resultado', 'Resultado ou consequência do evento conforme o documento'],
            ['local',        'Local do Evento',  'Onde ocorreu (se não modelado como entidade location separada)'],
            ['vitimas',      'Vítimas / Atingidos', 'Estimativa ou descrição de vítimas/atingidos quando constante na fonte'],
        ];

        foreach ($eventKeys as [$key, $label, $desc]) {
            $this->db->query(
                "INSERT INTO entity_attribute_vocabulary (entity_type, key_name, label, description)
                 VALUES ('event', ?, ?, ?)",
                [$key, $label, $desc]
            );
        }
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS entity_attribute_vocabulary");
    }
}
