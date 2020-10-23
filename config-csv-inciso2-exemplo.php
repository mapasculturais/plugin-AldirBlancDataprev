<?php
/**
 * Arquivo de configuração para o exportador dataprev no inciso 2
 * 
 * O Exportador do inciso 2 utiliza o nome dos campos para fazer a busca dos valores na base de dados na maiora dos casos, 
 * exeto para os campos abaixo. 
 * 
 * Apenas marque 1 nos campos que lhe se enquadrem na sua situação. lembramos que a base Mapas Culturais é uma mase homologada 
 * e pode ser usada para esse caso. Para isso marque 1 em FLAG_CAD_ESTADUAL
 * 
 * FLAG_CAD_ESTADUAL
 * FLAG_CAD_MUNICIPAL
 * FLAG_CAD_DISTRITAL
 * FLAG_CAD_SNIIC
 * 
 * Caso so campos acima sejam flegados como 1, os respectivos campos abaixo se preenchem automáticamente como nos exemplos.
 * Então basta deixalos como null aqui no arquivo de configuração.
 * 
 * SISTEMA_CAD_ESTADUAL = Nome do site ex.: (Mapa Cultural de São Paulo)
 * IDENTIFICADOR_CAD_ESTADUAL =  Numero da inscrição ex.: xx-47267711
 * 
 * SISTEMA_CAD_MUNICIPAL Nome do site ex.: (Mapa Cultural de São Paulo)
 * IDENTIFICADOR_CAD_MUNICIPAL Numero da inscrição ex.: xx-47267711
 * 
 * SISTEMA_CAD_DISTRITAL Nome do site ex.: (Mapa Cultural de São Paulo)
 * IDENTIFICADOR_CAD_DISTRITAL Numero da inscrição ex.: xx-47267711
 * 
 * INFORMAÇÕES IMPORTANTES
 * 
 * 1) No array (catergory), deve conter as categorias contidas no formulário do inciso 2, separadas em 2 em 2 chaves com_espaco_fisico
 * e sem_espaco_fisico
 * 
 * 2) No array (inscricoes_culturais), deve conter Os valores do campo INSCRIÇÃO EM CADASTRO CULTURAL do formilário. Caso esse não seja
 * o nome do campo, procure o campo que contenha os orgãos de cadastros culturais homologados e pegue as opções de respostas desse campo.
 * 
 * 3) O array (atuacoes-culturais) deve conter todos os valores das açoes culturais existentes no formulário separadas entre as categorias abaixo
 * - artes-cenicas
 * - artes-visuais
 * - audiovisual
 * - humanidades
 * - museu-memoria
 * - musica
 * - patrimonio-cultural
 * 
 * 4) Em caso de um determinado registro ser oriundo de vários campos com nomes diferentes, todos os campos que possam conter o valor devem 
 * ser colocados em um array, como por exemplo no campo NOME_ESPACO_CULTURAL citados nesse arquivo.
 */

return [
    "fields_cpf" => [ //Configuração para o arquivo CPF
        'CPF' => 'CPF',
        'SEXO' => 'Sexo',
        'NOME_ESPACO_CULTURAL' => [
            'Nome do coletivo',
            'Nome do Espaço Cultural',
        ],
        'FLAG_CAD_ESTADUAL' => 0,
        'SISTEMA_CAD_ESTADUAL' => null,
        'IDENTIFICADOR_CAD_ESTADUAL' => null,
        'FLAG_CAD_MUNICIPAL' => 1,
        'SISTEMA_CAD_MUNICIPAL' => null,
        'IDENTIFICADOR_CAD_MUNICIPAL' => null,
        'FLAG_CAD_DISTRITAL' => 0,
        'SISTEMA_CAD_DISTRITAL' => null,
        'IDENTIFICADOR_CAD_DISTRITAL' => null,
        'FLAG_CAD_NA_PONTOS_PONTOES' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_ES_PONTOS_PONTOES' => 0,
        'SISTEMA_CAD_ES_PONTOS_PONTOES' => null,
        'IDENTIFICADOR_CAD_ES_PONTOS_PONTOES' => null,
        'FLAG_CAD_SNIIC' => 0,
        'SISTEMA_CAD_SNIIC' => null,
        'IDENTIFICADOR_CAD_SNIIC' => null,
        'FLAG_CAD_SALIC' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_SICAB' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_OUTROS' => 'Inscrição em cadastro cultural',
        'SISTEMA_CAD_OUTROS' => null,
        'IDENTIFICADOR_CAD_OUTROS' => null,
        'FLAG_ATUACAO_ARTES_CENICAS' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_AUDIOVISUAL' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_MUSICA' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_ARTES_VISUAIS' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_PATRIMONIO_CULTURAL' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_MUSEUS_MEMORIA' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_HUMANIDADES' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
    ],
    "fields_cnpj" => [ //Configuração para o arquivo CNPJ
        'CNPJ' => [
            'CNPJ',
            'CNPJ',
        ],
        'FLAG_CAD_ESTADUAL' => 'Inscrição em cadastro cultural',
        'SISTEMA_CAD_ESTADUAL' => null,
        'IDENTIFICADOR_CAD_ESTADUAL' => null,
        'FLAG_CAD_MUNICIPAL' => 'Inscrição em cadastro cultural',
        'SISTEMA_CAD_MUNICIPAL' => null,
        'IDENTIFICADOR_CAD_MUNICIPAL' => null,
        'FLAG_CAD_DISTRITAL' => null,
        'SISTEMA_CAD_DISTRITAL' => null,
        'IDENTIFICADOR_CAD_DISTRITAL' => null,
        'FLAG_CAD_NA_PONTOS_PONTOES' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_ES_PONTOS_PONTOES' => null,
        'SISTEMA_CAD_ES_PONTOS_PONTOES' => null,
        'IDENTIFICADOR_CAD_ES_PONTOS_PONTOES' => null,
        'FLAG_CAD_SNIIC' => 'Inscrição em cadastro cultural',
        'SISTEMA_CAD_SNIIC' => null,
        'IDENTIFICADOR_CAD_SNIIC' => null,
        'FLAG_CAD_SALIC' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_SICAB' => 'Inscrição em cadastro cultural',
        'FLAG_CAD_OUTROS' => 'Inscrição em cadastro cultural',
        'SISTEMA_CAD_OUTROS' => null,
        'IDENTIFICADOR_CAD_OUTROS' => null,
        'FLAG_ATUACAO_ARTES_CENICAS' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_ARTES_CENICAS' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_AUDIOVISUAL' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_MUSICA' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_ARTES_VISUAIS' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_PATRIMONIO_CULTURAL' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_MUSEUS_MEMORIA' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
        'FLAG_ATUACAO_HUMANIDADES' => [
            'Área de atuação do espaço cultural',
            'Área de atuação do espaço cultural',
        ],
    ],
    "inscricoes_culturais" => [ // Opções inscrições culturais
        'mapa-cultural' => 'Cadastro Estadual de Cultura (Mapa Cultural)',
        'cadastro-municipal' => 'Cadastros Municipais de Cultura',
        'sniic' => 'Sistema Nacional de Informações e Indicadores Culturais',
        'salic' => 'Sistema de Apoio às Leis de incentivo à Cultura (Salic)',
        'sicab' => 'Sistema de Informações Cadastrais do Artesanato Brasileiro',
        'outros' => 'Outros cadastros referentes a atividades culturais',
    ],
    "category" => [ // Opções para categorias
        'com_espaco_fisico' => [
            'BENEFICIÁRIO COM CNPJ E ESPAÇO FÍSICO',
            'BENEFICIÁRIO COM CPF E ESPAÇO FÍSICO',
        ],
        'sem_espaco_fisico' => [
            'BENEFICIÁRIO COM CNPJ E SEM ESPAÇO FÍSICO',
            'BENEFICIÁRIO COM CPF E SEM ESPAÇO FÍSICO',
        ],
    ],
    'atuacoes-culturais' => [ // Opções para área de atuações culturais
        'artes-cenicas' => [
            'Artes Circenses',
            'Dança',
            'Teatro',
            'Ópera',
        ],
        'artes-visuais' => [
            'Artes Visuais',
            'Artesanato',
            'Design',
            'Fotografia',
            'Moda',
        ],
        'audiovisual' => [
            'Audiovisual',
        ],
        'humanidades' => [
            'Literatura',
        ],
        'museu-memoria' => [
            'Museu',
        ],
        'musica' => [
            'Música',
        ],
        'patrimonio-cultural' => [
            'Cultura Popular',
            'Gastronomia',
            'Outros',
            'Patrimônio Cultural',
        ],

    ],
    'herder_layout' => [ // Header padronizado segundo instruções do documento dataprev
        'REQUERENTE_CPF',
        'REQUERENTE_NOME',
        'REQUERENTE_DATA_NASCIMENTO',
        'REQUERENTE_CPF_SITUACAO',
        'NOME_ESPACO_CULTURAL',
        'REQUERENTE_CNPJ',
        'RAZAO_SOCIAL',
        'NOME_FANTASIA',
        'CNAE',
        'NATUREZA_JURIDICA',
        'REQUERENTE_CNPJ_SITUACAO',
        'DATA_CADASTRO',
        'ENTE_FEDERATIVO_CNPJ',
        'ENTE_FEDERATIVO_UF',
        'ENTE_FEDERATIVO_NOME',
        'SITUACAO_CADASTRO',
        'DATA_PROCESSAMENTO',
        'NM_ARQUIVO_LOTE',
        'SEGMENTO_ARTES_CENICAS',
        'SEGMENTO_ATUACAO_MUSICA',
        'SEGMENTO_ARTES_PATRIMONIO',
        'SEGMENTO_ARTES_HUMANI',
        'SEGMENTO_AUDIOVISUAL',
        'SEGMENTO_ARTES_VISUAIS',
        'SEGMENTO_MUSEUS_MEMORIAS',
        'IN_CAD_ESTAD_CULT',
        'SISTEMA_CAD_ESTAD_CULT',
        'IDENTIF_CAD_ESTAD_CULT',
        'IN_CAD_MUNIC_CULT',
        'SISTEMA_CAD_MUNIC_CULT',
        'IDENTIF_CAD_MUNIC_CULT',
        'IN_CAD_DISTR_CULT',
        'SISTEMA_CAD_DISTR_CULT',
        'IDENTIF_CAD_DISTR_CULT',
        'IN_CULTURA_VIVA_NACIONAL',
        'IN_CULTURA_VIVA_ESTADUAL',
        'SISTEMA_CULTURA_VIVA_ESTADUAL',
        'IDENTIF_CULTURA_VIVA_ESTADUAL',
        'IN_SNIIC',
        'SISTEMA_SNIIC',
        'IDENTIF_SNIIC',
        'IN_SALIC',
        'IN_SICAB',
        'IN_OUTROS_CAD',
        'SISTEMA_OUTROS_CAD',
        'IDENTIF_OUTROS_CAD',
        'IN_INSCRICAO_CADASTRO_HOMOL_CULTURA',
        'INDICADOR_INSCRIC_HOMOLOG_SICAB',
        'INDICADOR_INSCRIC_HOMOLOG_SALIC',
        'INDICADOR_INSCRIC_HOMOLOG_CULTURAVIVA',
        'IN_CPF_INCISO2: CPF faz parte do quadro societário de empresas que estão cadastradas no inciso 2.',
        'IN_CNPJ_INCISO2: Possui membros do quadro societário que também realizaram cadastro no inciso 2.',
        'IN_OBITO',
        'IN_BRASILEIRO_NO_EXTERIOR',
        'IN_DETENTO',
        'IN_CPF_PROCURADO_JUSTICA',
        'IN_CPF_POLIT_EXPOSTO',
        'DATA_REPROCESSAMENTO',
        'IN_PRESTACAO_CONTAS_CONFIRMADA',
        'DATA_PRESTACAO_CONTAS'
    ],
    'acceptance_parameters' => [ // Parametros de aceitação planilha

        'IN_OBITO' => [
            'positive' => [
                'Não'
            ],
            'negative' => [
                'Sim'
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente encontra-se inscrito no Sistema de Controle de Óbitos (SISOBI).',
            'impediment' => true,
        ],
        'IN_DETENTO' => [
            'positive' => [
                'Não'
            ],
            'negative' => [
                'Sim'
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente possui impedimento legal para o recebimento deste benefício (Cód.190dt).',
            'impediment' => true,
        ],
        'IN_CPF_PROCURADO_JUSTICA' => [
            'positive' => [
                'Não'
            ],
            'negative' => [
                'Sim'
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente possui impedimento legal para o recebimento deste benefício. (Cód.190pr).',
            'impediment' => true,
        ],
        'IN_CPF_INCISO2: CPF faz parte do quadro societário de empresas que estão cadastradas no inciso 2.' => [
            'positive' => [
                'Não'
            ],
            'negative' => [
                'Sim'
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente ou um de seus sócios já solicitou o benefício do subsídio mensal por uma entidade, empresa ou cooperativa cultural inscrita em CNPJ ao qual é responsável.',
            'impediment' => true,
        ],
        'IN_CNPJ_INCISO2: Possui membros do quadro societário que também realizaram cadastro no inciso 2.' => [
            'positive' => [
                'Não',
            ],
            'negative' => [
                'Sim'
            ],
            'response' => 'De acordo com o que consta na base dados federal, um ou mais sócios da entidade, empresa ou cooperativa cultural, inscrita em CNPJ, já solicitaram o benefício do subsídio mensal, na qualidade de pessoa física responsável por um espaço artístico e cultural ou por outra entidade, empresa ou cooperativa cultural, inscrita em CNPJ.',
            'impediment' => true
        ],
        'REQUERENTE_CPF_SITUACAO' => [
            'positive' => [
                0,
            ],
            'negative' => [
                2,
                3,
                4,
                5,
                8,
                9
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente não está com o CPF regular perante à Secretaria da Receita Federal do Brasil - SRFB',
            'impediment' => true,
        ],
        'REQUERENTE_CNPJ_SITUACAO' => [
            'positive' => [
                2,
            ],
            'negative' => [
                1,
                3,
                4,
                8
            ],
            'response' => 'De acordo com o que consta na base dados federal, o CNPJ do requerente não está ativo perante à Secretaria da Receita Federal do Brasil - SRFB.',
            'impediment' => true,
        ],
        'REQUERENTE_DATA_NASCIMENTO' => [
            'positive' => [
                18, //No arquivo DataPrev.php é validado se o requerente é maior ou igual a 18 anos de idade
            ],
            'response' => 'De acordo com o que consta na base dados federal, o requerente é menor de 18 anos.',
            'impediment' => true
        ],
        'NATUREZA_JURIDICA' => [
            'positive' => [
                '2062',
                '2070',
                '2089',
                '2135',
                '2143',
                '2232',
                '2240',
                '2259',
                '2267',
                '2305',
                '2313',
                '3069',
                '3239',
                '3999'
            ],
            'response' => 'De acordo com o que consta na base dados federal, a natureza jurídica do requerente é incompatível ao recebimento do benefício.',
            'impediment' => true,
        ],

    ],
    'validation_reference' => [ // Array de comparação que se é esperado após a verificação dos dados
        'REQUERENTE_DATA_NASCIMENTO' => true,
        'REQUERENTE_CPF_SITUACAO' => true,
        'NATUREZA_JURIDICA' => true,
        'REQUERENTE_CNPJ_SITUACAO' => true,
        'IN_CPF_INCISO2: CPF faz parte do quadro societário de empresas que estão cadastradas no inciso 2.' => true,
        'IN_CNPJ_INCISO2: Possui membros do quadro societário que também realizaram cadastro no inciso 2.' => true,
        'IN_OBITO' => true,
        'IN_DETENTO' => true,
        'IN_CPF_PROCURADO_JUSTICA' => true

    ],
    'validation_cad_cultural' => [ //Faz a verificação se o requerente esta incrito em um dos cadastros culturais listados nesse array
        'IN_CAD_ESTAD_CULT',
        'IN_CAD_MUNIC_CULT',
        'IN_CAD_DISTR_CULT',
        'IN_SNIIC',
        'IN_INSCRICAO_CADASTRO_CULT_HOMOLOG',
        'IN_INSCRIC_HOMOLOG_SICAB',
        'IN_INSCRIC_HOMOLOG_SALIC',
        'IN_SICAB',
        'IN_SALIC'
    ],
    'RegisterNumber' => 'IDENTIF_CAD_MUNIC_CULT' //Define qual será o campo utilizado para recuperar o número da inscrição na importação de dados
];

