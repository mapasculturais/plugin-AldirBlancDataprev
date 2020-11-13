<?php

namespace AldirBlancDataprev;

use DateTime;
use Exception;
use Normalizer;
use MapasCulturais\i;
use League\Csv\Reader;
use League\Csv\Writer;
use MapasCulturais\App;
use League\Csv\Statement;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use MapasCulturais\Entities\RegistrationEvaluation;

/**
 * Registration Controller
 *
 * By default this controller is registered with the id 'registration'.
 *
 *  @property-read \MapasCulturais\Entities\Registration $requestedEntity The Requested Entity
 */
// class AldirBlanc extends \MapasCulturais\Controllers\EntityController {
class Controller extends \MapasCulturais\Controllers\Registration
{
    protected $config = [];

    public function __construct()
    {
        parent::__construct();

        $app = App::i();

        $this->config = $app->plugins['AldirBlanc']->config;
        $this->config += $app->plugins['AldirBlancDataprev']->config;
        $this->entityClassName = '\MapasCulturais\Entities\Registration';
        $this->layout = 'aldirblanc';
    }

    protected function filterRegistrations(array $registrations) {
        $app = App::i();
        
        $_regs = [];

        $plugin = $app->plugins['AldirBlancDataprev'];
        $user = $plugin->getUser();
        $validador_recurso = $app->repo('User')->findOneBy(['email' => 'recurso@validador']);

        foreach ($registrations as $registration) {
            $dataprev_validation = $app->repo('RegistrationEvaluation')->findBy(['registration' => $registration, 'user' => $user]);
            $recurso = $validador_recurso ? 
                $app->repo('RegistrationEvaluation')->findBy(['registration' => $registration, 'user' => $validador_recurso, 'result' => '10']) :
                null;

            if($recurso || !$dataprev_validation){
                if ($this->config['exportador_requer_homologacao']) {
                    if (in_array($registration->consolidatedResult, ['10', 'homologado']) ) {
                        $_regs[] = $registration;
                    }
                } else {
                    $_regs[] = $registration;
                }
            }
        }
        
        return $_regs;
    }

    /**
     * Exportador para o inciso 1
     *
     * Implementa o sistema de exportação para a lei AldirBlanc no inciso 1
     * http://localhost:8080/dataprev/export_inciso1/status:1/from:2020-01-01/to:2020-01-30
     *
     * Parâmetros to e from não são obrigatórios, caso não informado retorna todos os registros no status de pendentes
     *
     * Parâmetro status não é obrigatório, caso não informado retorna todos com status 1
     *
     */
    public function ALL_export_inciso1()
    {
        //Seta o timeout
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');

        $this->requireAuthentication();
        $app = App::i();

        //Oportunidade que a query deve filtrar
        $opportunity_id = $this->config['inciso1_opportunity_id'];

        $parameter = $this->config['csv_inciso1']['parameters_csv_default'];

        //Satatus que a query deve filtrar
        $status = $parameter['status'] ?? 1;

        /**
         * Recebe e verifica os dados contidos no endpoint
         * http://localhost:8080/dataprev/export_inciso1/status:1/from:2020-01-01/to:2020-01-30
         * @var string $startDate
         * @var string $finishDate
         * @var \DateTime $date
         */
        $getdata = false;
        if (!empty($this->data)) {

            if (isset($this->data['from']) && isset($this->data['to'])) {

                if (!empty($this->data['from']) && !empty($this->data['to'])) {
                    if (!preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['from']) ||
                        !preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['to'])) {

                        throw new \Exception("O formato da data é inválido.");

                    } else {
                        //Data ínicial
                        $startDate = new DateTime($this->data['from']);
                        $startDate = $startDate->format('Y-m-d 00:00');

                        //Data final
                        $finishDate = new DateTime($this->data['to']);
                        $finishDate = $finishDate->format('Y-m-d 23:59');
                    }

                    $getdata = true;
                }

            }

            //Pega o status do endpoint
            $status = isset($this->data['status']) && is_numeric($this->data['status']) ? $this->data['status'] : $parameter['status'];

            //Pega o inciso do endpoint
            $inciso = isset($this->data['inciso']) && is_numeric($this->data['inciso']) ? $this->data['inciso'] : $parameter['status'];

        }

        $opportunity = $app->repo('Opportunity')->find($opportunity_id);
        $this->registerRegistrationMetadata($opportunity);

        if (!$opportunity->canUser('@control')) {
            echo "Não autorizado";
            die();
        }

        /**
         * Busca os registros no banco de dados         *
         * @var string $startDate
         * @var string $finishDate
         * @var string $dql
         * @var int $opportunity_id
         * @var array $key_registrations
         */
        if ($getdata) { //caso existe data como parâmetro, ele pega os registros do range de data selecionada com satatus 1
            $dql = "
            SELECT
                e
            FROM
                MapasCulturais\Entities\Registration e
            WHERE
                e.sentTimestamp >=:startDate AND
                e.sentTimestamp <= :finishDate AND
                e.status = :status AND
                e.opportunity = :opportunity_Id";

            $query = $app->em->createQuery($dql);

            $conditions = $query->setParameters([
                'opportunity_Id' => $opportunity_id,
                'startDate' => $startDate,
                'finishDate' => $finishDate,
                'status' => $status,
            ]);
            $registrations = $query->getResult();
        } else { //Se não exister data como parâmetro, ele retorna todos os registros com status 1
            $dql = "
            SELECT
                e
            FROM
                MapasCulturais\Entities\Registration e
            WHERE
                e.status = :status AND
                e.opportunity = :opportunity_Id";

            $query = $app->em->createQuery($dql);

            $conditions = $query->setParameters([
                'opportunity_Id' => $opportunity_id,
                'status' => $status,
            ]);
            
            $registrations = $query->getResult();
        }

        $registrations = $this->filterRegistrations($registrations);        

        if (empty($registrations)) {
            echo "Não foram encontrados registros.";
            die();
        }

        /**
         * Array com header do documento CSV
         * @var array $headers
         */
        $headers = [
            "CPF",
            "SEXO",
            "FLAG_CAD_ESTADUAL",
            "SISTEMA_CAD_ESTADUAL",
            "IDENTIFICADOR_CAD_ESTADUAL",
            "FLAG_CAD_MUNICIPAL",
            "SISTEMA_CAD_MUNICIPAL",
            "IDENTIFICADOR_CAD_MUNICIPAL",
            "FLAG_CAD_DISTRITAL",
            "SISTEMA_CAD_DISTRITAL",
            "IDENTIFICADOR_CAD_DISTRITAL",
            "FLAG_CAD_SNIIC",
            "SISTEMA_CAD_SNIIC",
            "IDENTIFICADOR_CAD_SNIIC",
            "FLAG_CAD_SALIC",
            "FLAG_CAD_SICAB",
            "FLAG_CAD_OUTROS",
            "SISTEMA_CAD_OUTROS",
            "IDENTIFICADOR_CAD_OUTROS",
            "FLAG_ATUACAO_ARTES_CENICAS",
            "FLAG_ATUACAO_AUDIOVISUAL",
            "FLAG_ATUACAO_MUSICA",
            "FLAG_ATUACAO_ARTES_VISUAIS",
            "FLAG_ATUACAO_PATRIMONIO_CULTURAL",
            "FLAG_ATUACAO_MUSEUS_MEMORIA",
            "FLAG_ATUACAO_HUMANIDADES",
            "FAMILIARCPF",
            "GRAUPARENTESCO",
        ];

        /**
         * Mapeamento de campos do documento CSV
         * @var array $fields
         */
        $csv_conf = $this->config['csv_inciso1'];

        $fields = [
            "CPF" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["CPF"];
                return str_replace(['.', '-'], '', $registrations->$field_id);

            },
            'SEXO' => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["SEXO"];

                if ($registrations->$field_id == 'Masculino') {
                    return 1;

                } else if ($registrations->$field_id == 'Feminino') {
                    return 2;

                } else {
                    return 0;
                }

            },
            "FLAG_CAD_ESTADUAL" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_CAD_ESTADUAL"];
                return $field_id;

            },
            "SISTEMA_CAD_ESTADUAL" => function ($registrations) use ($csv_conf, $app) {
                return $csv_conf['FLAG_CAD_ESTADUAL'] ? $app->view->dict('site: name', false) : '';

            },
            "IDENTIFICADOR_CAD_ESTADUAL" => function ($registrations) use ($csv_conf) {
                return $csv_conf['FLAG_CAD_ESTADUAL'] ? $registrations->number : '';

            },
            "FLAG_CAD_MUNICIPAL" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_MUNICIPAL"];

            },
            "SISTEMA_CAD_MUNICIPAL" => function ($registrations) use ($csv_conf, $app) {
                return $csv_conf['FLAG_CAD_MUNICIPAL'] ? $app->view->dict('site: name', false) : '';

            },
            "IDENTIFICADOR_CAD_MUNICIPAL" => function ($registrations) use ($csv_conf) {
                return $csv_conf['FLAG_CAD_MUNICIPAL'] ? $registrations->number : '';

            },
            "FLAG_CAD_DISTRITAL" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_DISTRITAL"];

            },
            "SISTEMA_CAD_DISTRITAL" => function ($registrations) use ($csv_conf, $app) {
                return $csv_conf['FLAG_CAD_DISTRITAL'] ? $app->view->dict('site: name', false) : '';

            },
            "IDENTIFICADOR_CAD_DISTRITAL" => function ($registrations) use ($csv_conf) {
                return $csv_conf['FLAG_CAD_DISTRITAL'] ? $registrations->number : '';

            },
            "FLAG_CAD_SNIIC" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_SNIIC"];

            },
            "SISTEMA_CAD_SNIIC" => function ($registrations) use ($csv_conf, $app) {
                return $csv_conf['FLAG_CAD_SNIIC'] ? $app->view->dict('site: name', false) : '';

            },
            "IDENTIFICADOR_CAD_SNIIC" => function ($registrations) use ($csv_conf) {
                return $csv_conf['FLAG_CAD_SNIIC'] ? $registrations->number : '';
            },
            "FLAG_CAD_SALIC" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_SALIC"];

            },
            "FLAG_CAD_SICAB" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_SICAB"];

            },
            "FLAG_CAD_OUTROS" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FLAG_CAD_OUTROS"];

            },
            "SISTEMA_CAD_OUTROS" => function ($registrations) use ($csv_conf) {
                return $csv_conf["SISTEMA_CAD_OUTROS"];

            },
            "IDENTIFICADOR_CAD_OUTROS" => function ($registrations) use ($csv_conf) {
                return $csv_conf["IDENTIFICADOR_CAD_OUTROS"];

            },
            "FLAG_ATUACAO_ARTES_CENICAS" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_ARTES_CENICAS"];

                $options = $csv_conf['atuacoes-culturais']['artes-cenicas'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }

                return $result;
            },
            "FLAG_ATUACAO_AUDIOVISUAL" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_AUDIOVISUAL"];

                $options = $csv_conf['atuacoes-culturais']['audiovisual'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }

                return $result;
            },
            "FLAG_ATUACAO_MUSICA" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_MUSICA"];

                $options = $csv_conf['atuacoes-culturais']['musica'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }

                return $result;
            },
            "FLAG_ATUACAO_ARTES_VISUAIS" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_MUSICA"];

                $options = $csv_conf['atuacoes-culturais']['artes-visuais'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }

                return $result;
            },
            "FLAG_ATUACAO_PATRIMONIO_CULTURAL" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_PATRIMONIO_CULTURAL"];

                $options = $csv_conf['atuacoes-culturais']['patrimonio-cultural'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }
                return $result;
            },
            "FLAG_ATUACAO_MUSEUS_MEMORIA" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_MUSEUS_MEMORIA"];

                $options = $csv_conf['atuacoes-culturais']['museu-memoria'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }
                return $result;
            },
            "FLAG_ATUACAO_HUMANIDADES" => function ($registrations) use ($csv_conf) {
                $field_id = $csv_conf["FLAG_ATUACAO_MUSEUS_MEMORIA"];

                $options = $csv_conf['atuacoes-culturais']['humanidades'];
                $temp = (array) $registrations->$field_id;

                $result = 0;
                foreach ($options as $value) {
                    if (in_array($value, $temp)) {
                        $result = 1;
                    }
                }

                return $result;
            },
            "FAMILIARCPF" => function ($registrations) use ($csv_conf) {
                return $csv_conf["FAMILIARCPF"];

            },
            "GRAUPARENTESCO" => function ($registrations) use ($csv_conf) {
                return $csv_conf["GRAUPARENTESCO"];

            },
        ];

        /**
         * Itera sobre os registros mapeados
         * @var array $data_candidate
         * @var array $data_familyGroup
         * @var int $cpf
         */
        $data_candidate = [];
        $data_familyGroup = [];
        foreach ($registrations as $key_registration => $registration) {
            $cpf_candidate = '';
            foreach ($fields as $key_fields => $field) {
                if ($key_fields != "FAMILIARCPF" && $key_fields != "GRAUPARENTESCO") {
                    if (is_callable($field)) {
                        $data_candidate[$key_registration][$key_fields] = $field($registration);

                        if ($key_fields == "CPF") {
                            $cpf_candidate = $field($registration);
                        }

                    } else if (is_string($field) && strlen($field) > 0) {
                        $data_candidate[$key_registration][$key_fields] = $registration->$field;

                    } else {
                        $data_candidate[$key_registration][$key_fields] = $field;

                    }
                } else {
                    $data_candidate[$key_registration][$key_fields] = null;
                    $_field = $field($registrations);

                    if (is_array($registration->$_field)) {
                        foreach ($registration->$_field as $key_familyGroup => $familyGroup) {
                            if (!isset($familyGroup->cpf) || !isset($familyGroup->relationship)) {
                                continue;
                            }

                            foreach ($headers as $key => $header) {
                                if ($header == "CPF") {
                                    $data_familyGroup[$key_registration][$key_familyGroup][$header] = $cpf_candidate;

                                } elseif ($header == "FAMILIARCPF") {
                                    $data_familyGroup[$key_registration][$key_familyGroup][$header] = str_replace(['.', '-'], '', $familyGroup->cpf);

                                } elseif ($header == "GRAUPARENTESCO") {
                                    $data_familyGroup[$key_registration][$key_familyGroup][$header] = $familyGroup->relationship;

                                } else {
                                    $data_familyGroup[$key_registration][$key_familyGroup][$header] = null;

                                }
                            }

                        }
                    }
                }
            }
        }

        /**
         * Prepara as linhas do CSV
         * @var array $data_candidate
         * @var array $data_familyGroup
         * @var array $headers
         * @var array $data
         */
        foreach ($data_candidate as $key_candidate => $candidate) {
            $data[] = $candidate;

            if (isset($data_familyGroup[$key_candidate])) {
                foreach ($data_familyGroup[$key_candidate] as $key_familyGroup => $familyGroup) {

                    foreach ($headers as $key_header => $header) {

                        if ($header == "FAMILIARCPF") {
                            $data[] = $familyGroup;
                        }
                    }
                }
            }
        }

        $file_name = 'inciso1-'.$opportunity_id."-". md5(json_encode($data)) . '.csv';

        $dir = PRIVATE_FILES_PATH . 'aldirblanc/inciso1/';

        $patch = $dir . $file_name;

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $stream = fopen($patch, 'w');

        $csv = Writer::createFromStream($stream);

        $csv->insertOne($headers);

        foreach ($data as $key_csv => $csv_line) {
            $csv->insertOne($csv_line);
        }

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . $file_name);
        header('Pragma: no-cache');
        readfile($patch);
    }

    /**
     * Exportador para o inciso 2
     *
     * Implementa o sistema de exportação para a lei AldirBlanc no inciso 2
     * http://localhost:8080/dataprev/export_inciso2/opportunity:6/status:1/type:cpf/from:2020-01-01/to:2020-01-30
     *
     * Parâmetros to e from não são obrigatórios, caso nao informado retorna todos os registros no status de pendentes
     *
     * Parâmetro type se alterna entre cpf e cnpj
     *
     * Parâmetro status não é obrigatório, caso não informado retorna todos com status 1
     *
     */
    public function ALL_export_inciso2()
    {

        //Seta o timeout
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');

        $this->requireAuthentication();
        $app = App::i();
        
        $inciso2_opportunity_ids = $this->config['inciso2_opportunity_ids'];
        
        $opportunity_ids = [];
        $oppId = "";
        //Oportunidades que a query deve filtrar
        $opportunities = [];
        if (isset($this->data['project'])) {
            $project = $app->repo('Project')->find($this->data['project']);

            $project_ids = array_merge([$project->id], $project->getChildrenIds());

            $opportunities = $app->repo('ProjectOpportunity')->findBy(['ownerEntity' => $project_ids, 'id' => $inciso2_opportunity_ids]);

            $opportunity_ids = array_map(function($opp) { return $opp->id; }, $opportunities);
            
        } else if (isset($this->data['opportunity'])) {
            if (!in_array($this->data['opportunity'], $inciso2_opportunity_ids)) {
                echo "a oportunidade de id {$this->data['opportunity']} não é uma oportunidade da lei Aldir Blanc";
                die;
            }
            $opportunity_ids = [$this->data['opportunity']];
            $opportunities = [$app->repo('Opportunity')->find($this->data['opportunity'])];
            $oppId = $this->data['opportunity']."-";

        } else {
            echo 'informe a oportunidede (opportunity=id) ou o projeto (project=id)';
            die;
        }

        if (empty($opportunity_ids)) {
            echo 'nenhuma oportunidade da válida encontrada';
            die;
        }

        //Satatus que a query deve filtrar
        $status = 1;

        //Inciso que a query deve filtrar
        $inciso = 1;

        /**
         * Recebe e verifica os dados contidos no endpoint
         * https://localhost:8080/dataprev_inciso2/export/opportunity:2/from:2020-09-01/to:2020-09-30/
         * @var string $startDate
         * @var string $finishDate
         * @var \DateTime $date
         */
        $getData = false;
        if (!empty($this->data)) {

            if (isset($this->data['from']) && isset($this->data['to'])) {

                if (!empty($this->data['from']) && !empty($this->data['to'])) {
                    if (!preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['from']) ||
                        !preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['to'])) {

                        throw new \Exception("O formato da data é inválido.");

                    } else {
                        //Data ínicial
                        $startDate = new DateTime($this->data['from']);
                        $startDate = $startDate->format('Y-m-d 00:00');

                        //Data final
                        $finishDate = new DateTime($this->data['to']);
                        $finishDate = $finishDate->format('Y-m-d 23:59');
                    }
                    $getData = true;
                }

            }

            //Pega o status do endpoint
            $status = isset($this->data['status']) && is_numeric($this->data['status']) ? $this->data['status'] : 1;

            if (isset($this->data['type']) && preg_match("/^[a-z]{3,4}$/", $this->data['type'])) {
                $type = $this->data['type'];

            } else {
                throw new Exception("Informe o tipo de exportação EX.: type:cpf ou type:cnpj");
            }

        } else {
            throw new Exception("Informe a oportunidade! Ex.: opportunity:2");

        }

        /**
         * Pega a oprtunidade se ainda não pegou
         */
        foreach($opportunities as $opp) {
            $this->registerRegistrationMetadata($opp);

            if (!$opp->canUser('@control')) {
                echo "Não autorizado a exportar da oportunidade {$opp->id}";
                die;
            }
        }


        /**
         * Busca os registros no banco de dados
         * @var string $startDate
         * @var string $finishDate
         * @var string $dql
         * @var int $opportunity_id
         * @var array $key_registrations
         */
        if ($getData) { //caso existe data como parametro ele pega o range da data selecionada com satatus 1
            $dql = "
            SELECT
                e
            FROM
                MapasCulturais\Entities\Registration e
            WHERE
                e.sentTimestamp >=:startDate AND
                e.sentTimestamp <= :finishDate AND
                e.status = :status AND
                e.opportunity IN (:opportunities_id)";

            $query = $app->em->createQuery($dql);
            $query->setParameters([
                'opportunities_id' => $opportunity_ids,
                'startDate' => $startDate,
                'finishDate' => $finishDate,
                'status' => $status,
            ]);
            $registrations = $query->getResult();

        } else { //Se não exister data como parametro ele retorna todos os registros com status 1
            $dql = "
            SELECT
                e
            FROM
                MapasCulturais\Entities\Registration e
            WHERE
                e.status = :status AND
                e.opportunity IN(:opportunities_id)";

            $query = $app->em->createQuery($dql);
            $query->setParameters([
                'opportunities_id' => $opportunity_ids,
                'status' => $status,
            ]);
            $registrations = $query->getResult();
        }

        $registrations = $this->filterRegistrations($registrations);

        if (empty($registrations)) {
            echo "Não foram encontrados registros.";
            die();
        }

        /**
         * pega as configurações do CSV no arquivo config-csv-inciso2.php
         */
        $csv_conf = $this->config['csv_inciso2'];
        $inscricoes = $this->config['csv_inciso2']['inscricoes_culturais'];
        $atuacoes = $this->config['csv_inciso2']['atuacoes-culturais'];
        $category = $this->config['csv_inciso2']['category'];

        $data_candidate_cpf = [];
        $data_candidate_cnpj = [];

        foreach ($opportunities as $opportunity) {

            $field_labelMap = [];

            /**
             * Mapeamento de fielsds_id pelo label do campo
             */
            foreach ($opportunity->registrationFieldConfigurations as $field) {
                $field_labelMap["field_" . $field->id] = trim($field->title);

            }

            /**
             * Faz o mapeamento do field_id pelo label do campo para requerentes do tipo CPF
             *
             * Esta sendo feito uma comparação de string, coloque no arquivo de configuração
             * exatamente o texto do label desejado
             */
            foreach ($csv_conf['fields_cpf'] as $key_csv_conf => $field) {
                if (is_array($field)) {
                    $value = array_unique($field);

                    if (count($value) == 1) {
                        foreach ($field as $key => $value) {
                            $field_temp = array_keys($field_labelMap, $value);
                        }

                    } else {

                        $field_temp = [];
                        foreach ($field as $key => $value) {
                            $field_temp[] = array_search(trim($value), $field_labelMap);

                        }

                    }
                    $fields_cpf[$key_csv_conf] = $field_temp;

                } else {
                    $field_temp = array_search(trim($field), $field_labelMap);
                    $fields_cpf[$key_csv_conf] = $field_temp ? $field_temp : $field;

                }
            }

            /**
             * Faz o mapeamento do field_id pelo label do campo para requerentes do tipo CPF
             *
             * Esta sendo feito uma comparação de string, coloque no arquivo de configuração
             * exatamente o texto do label desejado
             */
            foreach ($csv_conf['fields_cnpj'] as $key_csv_conf => $field) {
                if (is_array($field)) {

                    $value = array_unique($field);

                    if (count($value) == 1) {
                        foreach ($field as $key => $value) {
                            $field_temp = array_keys($field_labelMap, $value);
                        }

                    } else {

                        $field_temp = [];
                        foreach ($field as $key => $value) {
                            $field_temp[] = array_search(trim($value), $field_labelMap);

                        }

                    }
                    $fields_cnpj[$key_csv_conf] = $field_temp;

                } else {
                    $field_temp = array_search(trim($field), $field_labelMap);
                    $fields_cnpj[$key_csv_conf] = $field_temp ? $field_temp : $field;

                }
            }

            /**
             * Mapeia os fields para um requerente pessoa física
             */
            $fields_cpf_ = [
                'CPF' => function ($registrations) use ($fields_cpf) {
                    $field_id = $fields_cpf['CPF'];
                    return str_replace(['.', '-'], '', $registrations->$field_id);

                },
                'SEXO' => function ($registrations) use ($fields_cpf) {
                    $field_id = $fields_cpf['SEXO'];

                    if ($registrations->$field_id == 'Masculino') {
                        return 1;

                    } else if ($registrations->$field_id == 'Feminino') {
                        return 2;

                    } else {
                        return 0;
                    }

                },
                'NOME_ESPACO_CULTURAL' => function ($registrations) use ($fields_cpf) {
                    $field_id = $fields_cpf['NOME_ESPACO_CULTURAL'];

                    $result = "";
                    if (is_array($field_id)) {
                        foreach ($field_id as $value) {
                            if (!$value) {
                                continue;
                            }
                            if($registrations->$value){
                                $result = $registrations->$value;
                                break;
                            }
                        }
                    } else {
                        $result = $registrations->$field_id ? $registrations->$field_id : '';

                    }

                    return $result;
                },
                'FLAG_CAD_ESTADUAL' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    $field_id = $fields_cpf["FLAG_CAD_ESTADUAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }
                },
                'SISTEMA_CAD_ESTADUAL' => function ($registrations) use ($fields_cpf, $app, $inscricoes) {
                    return $fields_cpf['FLAG_CAD_ESTADUAL'] ? $app->view->dict('site: name', false) : '';
                },
                'IDENTIFICADOR_CAD_ESTADUAL' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    return $fields_cpf['FLAG_CAD_ESTADUAL'] ? $registrations->number : '';

                },
                'FLAG_CAD_MUNICIPAL' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    $field_id = $fields_cpf["FLAG_CAD_MUNICIPAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }
                    
                },
                'SISTEMA_CAD_MUNICIPAL' => function ($registrations) use ($fields_cpf, $app, $inscricoes) {                
                    return $fields_cpf['FLAG_CAD_MUNICIPAL'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_MUNICIPAL' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    return $fields_cpf['FLAG_CAD_MUNICIPAL'] ? $registrations->number : '';
                },
                'FLAG_CAD_DISTRITAL' => function ($registrations) use ($fields_cpf) {
                    $field_id = $fields_cpf["FLAG_CAD_DISTRITAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }

                },
                'SISTEMA_CAD_DISTRITAL' => function ($registrations) use ($fields_cpf, $app) {
                    return $fields_cpf['FLAG_CAD_DISTRITAL'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_DISTRITAL' => function ($registrations) use ($fields_cpf) {
                    return $fields_cpf['FLAG_CAD_DISTRITAL'] ? $registrations->number : '';

                },
                'FLAG_CAD_NA_PONTOS_PONTOES' => function ($registrations) use ($fields_cnpj) {
                    $field_id = $fields_cnpj["FLAG_CAD_NA_PONTOS_PONTOES"];

                    $option = 'Cadastro Nacional de Pontos e Pontões de Cultura';

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }
                    return $result;

                },
                'FLAG_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cpf) {
                    return 0;
                },
                'SISTEMA_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cpf) {
                    return '';
                },
                'IDENTIFICADOR_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cpf) {
                    return '';
                },
                'FLAG_CAD_SNIIC' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    $field_id = $fields_cpf["FLAG_CAD_SNIIC"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }


                },
                'SISTEMA_CAD_SNIIC' => function ($registrations) use ($fields_cpf, $inscricoes, $app) {
                    return $fields_cpf['FLAG_CAD_SNIIC'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_SNIIC' => function ($registrations) use ($fields_cpf, $inscricoes) {
                    return $fields_cpf['FLAG_CAD_SNIIC'] ? $registrations->number : '';
                },
                'FLAG_CAD_SALIC' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_SALIC"];

                    $option = $inscricoes['salic'];

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }

                    return $result;
                },
                'FLAG_CAD_SICAB' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_SICAB"];

                    $option = $inscricoes['sicab'];

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }

                    return $result;

                },
                'FLAG_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return 0;

                },
                'SISTEMA_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return "";

                },
                'IDENTIFICADOR_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return "";

                },
                'FLAG_ATUACAO_ARTES_CENICAS' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes, $category) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_ARTES_CENICAS'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['artes-cenicas'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_AUDIOVISUAL' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_AUDIOVISUAL'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['audiovisual'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;

                },
                'FLAG_ATUACAO_MUSICA' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_MUSICA'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['musica'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_ARTES_VISUAIS' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_ARTES_VISUAIS'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['artes-visuais'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;

                },
                'FLAG_ATUACAO_PATRIMONIO_CULTURAL' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_PATRIMONIO_CULTURAL'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['patrimonio-cultural'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_MUSEUS_MEMORIA' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_MUSEUS_MEMORIA'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['museu-memoria'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_HUMANIDADES' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_HUMANIDADES'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['humanidades'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
            ];

            /**
             * Mapeia os fields para um requerente pessoa jurídica
             */
            $fields_cnpj_ = [
                'CNPJ' => function ($registrations) use ($fields_cnpj) {
                    $field_temp = $fields_cnpj['CNPJ'];
                    $field_id = null;

                    if (is_array($field_temp)) {
                        foreach ($field_temp as $value) {

                            if ($registrations->$value) {
                                $field_id = $value;
                            }
                        }
                    } else {
                        $field_id = $field_temp;
                    }
                    if ($field_id){
                        return str_replace(['.', '-', '/'], '', $registrations->$field_id);
                    } else {
                        return null;
                    }

                }, 'FLAG_CAD_ESTADUAL' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_ESTADUAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }

                },
                'SISTEMA_CAD_ESTADUAL' => function ($registrations) use ($fields_cnpj, $app, $inscricoes) {
                    return $fields_cnpj['FLAG_CAD_ESTADUAL'] ? $app->view->dict('site: name', false) : '';
                },
                'IDENTIFICADOR_CAD_ESTADUAL' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return $fields_cnpj['FLAG_CAD_ESTADUAL'] ? $registrations->number : '';

                },
                'FLAG_CAD_MUNICIPAL' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_MUNICIPAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }

                },
                'SISTEMA_CAD_MUNICIPAL' => function ($registrations) use ($fields_cnpj, $inscricoes, $app) {
                    return $fields_cnpj['FLAG_CAD_MUNICIPAL'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_MUNICIPAL' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return $fields_cnpj['FLAG_CAD_MUNICIPAL'] ? $registrations->number : '';

                },
                'FLAG_CAD_DISTRITAL' => function ($registrations) use ($fields_cnpj) {
                    $field_id = $fields_cnpj["FLAG_CAD_DISTRITAL"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }

                },
                'SISTEMA_CAD_DISTRITAL' => function ($registrations) use ($fields_cnpj, $app) {
                    return $fields_cnpj['FLAG_CAD_DISTRITAL'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_DISTRITAL' => function ($registrations) use ($fields_cnpj) {
                    return $fields_cnpj['FLAG_CAD_DISTRITAL'] ? $registrations->number : '';

                },
                'FLAG_CAD_NA_PONTOS_PONTOES' => function ($registrations) use ($fields_cnpj) {
                    $field_id = $fields_cnpj["FLAG_CAD_NA_PONTOS_PONTOES"];

                    $option = 'Cadastro Nacional de Pontos e Pontões de Cultura';

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }
                    return $result;

                },
                'FLAG_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cnpj) {
                    return 0;
                },
                'SISTEMA_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cnpj) {
                    return "";
                },
                'IDENTIFICADOR_CAD_ES_PONTOS_PONTOES' => function ($registrations) use ($fields_cnpj) {
                    return "";
                },
                'FLAG_CAD_SNIIC' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_SNIIC"];
                    
                    if($field_id){
                        return 1;
                    }else{
                        return 0;
                    }

                },
                'SISTEMA_CAD_SNIIC' => function ($registrations) use ($fields_cnpj, $inscricoes, $app) {
                    return $fields_cnpj['FLAG_CAD_SNIIC'] ? $app->view->dict('site: name', false) : '';

                },
                'IDENTIFICADOR_CAD_SNIIC' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return $fields_cnpj['FLAG_CAD_SNIIC'] ? $registrations->number : '';

                },
                'FLAG_CAD_SALIC' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_SALIC"];

                    $option = $inscricoes['salic'];

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }

                    return $result;
                },
                'FLAG_CAD_SICAB' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    $field_id = $fields_cnpj["FLAG_CAD_SICAB"];

                    $option = $inscricoes['sicab'];

                    $result = 0;

                    if (is_array($registrations->$field_id)) {
                        if ($field_id && in_array($option, $registrations->$field_id)) {
                            $result = 1;
                        }

                    } else {
                        if ($field_id && $registrations->$field_id == $option) {
                            $result = 1;
                        }

                    }

                    return $result;

                },
                'FLAG_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    
                    return 0;

                },
                'SISTEMA_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return "";

                },
                'IDENTIFICADOR_CAD_OUTROS' => function ($registrations) use ($fields_cnpj, $inscricoes) {
                    return "";

                },
                'FLAG_ATUACAO_ARTES_CENICAS' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes, $category) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_ARTES_CENICAS'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['artes-cenicas'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_AUDIOVISUAL' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_AUDIOVISUAL'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['audiovisual'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;

                },
                'FLAG_ATUACAO_MUSICA' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_MUSICA'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['musica'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_ARTES_VISUAIS' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_ARTES_VISUAIS'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['artes-visuais'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;

                },
                'FLAG_ATUACAO_PATRIMONIO_CULTURAL' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_PATRIMONIO_CULTURAL'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['patrimonio-cultural'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_MUSEUS_MEMORIA' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_MUSEUS_MEMORIA'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['museu-memoria'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
                'FLAG_ATUACAO_HUMANIDADES' => function ($registrations) use ($csv_conf, $fields_cnpj, $atuacoes) {
                    $field_temp = $fields_cnpj['FLAG_ATUACAO_HUMANIDADES'];

                    if (is_array($field_temp)) {

                        $field_id = [];
                        foreach (array_filter($field_temp) as $key => $value) {
                            if (!$field_id) {
                                if ($registrations->$value) {
                                    $field_id = $registrations->$value;

                                } else {
                                    $field_id = [];

                                }
                            }
                        }
                    } else {
                        $field_id = $registrations->$field_temp;
                    }

                    $options = $atuacoes['humanidades'];

                    $result = 0;
                    foreach ($options as $value) {

                        if (in_array($value, $field_id)) {
                            $result = 1;
                        }
                    }

                    return $result;
                },
            ];

            /**
             * Itera sobre os dados mapeados
             */
            foreach ($registrations as $key_registration => $registration) {
                
                //Verifica qual tipo de candidato se trata no  cadastro se e pessoa física ou pessoa jurídica
                if (in_array($registration->category, ['BENEFICIÁRIO COM CPF E ESPAÇO FÍSICO', 'BENEFICIÁRIO COM CPF E SEM ESPAÇO FÍSICO'])) {
                    $type_candidate = 'fields_cpf_';
                } else if (in_array($registration->category, ['BENEFICIÁRIO COM CNPJ E SEM ESPAÇO FÍSICO', 'BENEFICIÁRIO COM CNPJ E ESPAÇO FÍSICO'])) {
                    $type_candidate = 'fields_cnpj_';
                } else {
                    die("inscrição: {$registration->number} - categoria '{$registration->category}' inválida");
                }
                
                /**
                 * Faz a separação dos candidatos
                 *
                 * $data_candidate_cpf recebe pessoas físicas
                 * $data_candidate_cnpj recebe pessoas jurídicas
                 */
                foreach ($$type_candidate as $key_fields => $field) {

                    if ($type_candidate == 'fields_cnpj_') {

                        if (is_callable($field)) {
                            $data_candidate_cnpj[$key_registration][$key_fields] = $field($registration);

                        } else if (is_string($field) && strlen($field) > 0) {

                            $data_candidate_cnpj[$key_registration][$key_fields] = $registration->$field;

                        } else {

                            $data_candidate_cnpj[$key_registration][$key_fields] = $field;

                        }
                    } else {
                        if (is_callable($field)) {
                            $data_candidate_cpf[$key_registration][$key_fields] = $field($registration);

                        } else if (is_string($field) && strlen($field) > 0) {

                            $data_candidate_cpf[$key_registration][$key_fields] = $registration->$field;

                        } else {

                            $data_candidate_cpf[$key_registration][$key_fields] = $field;

                        }
                    }

                }
            }
        }

        //Cria o CSV para pessoa jurídica
        if ($type == 'cnpj') {
            $file_name = 'inciso2-CNPJ-'.$oppId. md5(json_encode($data_candidate_cnpj)) . '.csv';

            $dir = PRIVATE_FILES_PATH . 'aldirblanc/inciso2/cnpj/';

            $patch = $dir . $file_name;

            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }

            $stream = fopen($patch, 'w');

            $csv = Writer::createFromStream($stream);

            $field_temp = $csv_conf['fields_cnpj'];

            foreach ($field_temp as $key => $value) {
                $header_cnpj[] = $key;
            }

            $csv->insertOne($header_cnpj);

            foreach ($data_candidate_cnpj as $key_csv => $csv_line) {
                $csv->insertOne($csv_line);
            }

            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename=' . $file_name);
            header('Pragma: no-cache');
            readfile($patch);
        }

        //Cria o CSV para pessoa física
        if ($type == 'cpf') {
            $file_name = 'inciso2-CPF-'.$oppId. md5(json_encode($data_candidate_cpf)) . '.csv';

            $dir = PRIVATE_FILES_PATH . 'aldirblanc/inciso2/cpf/';

            $patch = $dir . $file_name;

            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }

            $stream = fopen($patch, 'w');

            $csv = Writer::createFromStream($stream);

            $field_temp = $csv_conf['fields_cpf'];

            foreach ($field_temp as $key => $value) {
                $header_cpf[] = $key;
            }

            $csv->insertOne($header_cpf);

            foreach ($data_candidate_cpf as $key_csv => $csv_line) {
                $csv->insertOne($csv_line);
            }

            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename=' . $file_name);
            header('Pragma: no-cache');
            readfile($patch);
        }
    }

    /**
     * Exportador para o inciso 3
     *Implementa o sistema de exportação para a lei AldirBlanc no inciso 3
     * 
     */
    public function ALL_export_inciso3()
    {
        //Seta o timeout
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1228M');

        //garante que o usuário esteja autenticado
        $this->requireAuthentication();
        $app = App::i(); 

        //Carrega os dados de configurações do inciso 3
        $csv_config = $this->config['csv_inciso3'];
        $opportunities = $csv_config['opportunities'];
        $status = $csv_config['parameters_csv_default']['status'];
        $header = $csv_config['header'];
        
        /**
         * Recebe e verifica os dados contidos no endpoint
         * https://localhost:8080/dataprev_inciso2/export/opportunity:2/from:2020-09-01/to:2020-09-30/
         * @var string $startDate
         * @var string $finishDate
         * @var \DateTime $date
         */
        $getData = false;
        if (!empty($this->data)) {

            if (isset($this->data['from']) && isset($this->data['to'])) {

                if (!empty($this->data['from']) && !empty($this->data['to'])) {
                    if (!preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['from']) ||
                        !preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $this->data['to'])) {

                        throw new \Exception("O formato da data é inválido.");

                    } else {
                        //Data ínicial
                        $startDate = new DateTime($this->data['from']);
                        $startDate = $startDate->format('Y-m-d 00:00');

                        //Data final
                        $finishDate = new DateTime($this->data['to']);
                        $finishDate = $finishDate->format('Y-m-d 23:59');
                    }
                    $getData = true;
                }

            }
            

            //Pega a oportunidade do endpoint
            if (!isset($this->data['opportunity']) || empty($this->data['opportunity'])) {
                throw new Exception("Informe a oportunidade! Ex.: opportunity:2");

            } elseif (!is_numeric($this->data['opportunity'])) {
                throw new Exception("Oportunidade inválida");

            } else {
                $opportunity_id = $this->data['opportunity'];
            }

            
        } else {
            throw new Exception("Informe a oportunidade! Ex.: opportunity:2");

        }       
        
        $opportunity = $app->repo('Opportunity')->find($opportunity_id);
        $this->registerRegistrationMetadata($opportunity);

        //Bloqueia o acesso a oportunidade caso usuário não tenha o acesso devido
        if (!$opportunity->canUser('@control')) {
            echo "Não autorizado acesso a oportunidade " . $opportunity_id;
            die();
        }

        if($getData){
            $dql = "SELECT e FROM MapasCulturais\Entities\Registration e
            WHERE e.status = :status AND 
            e.opportunity = :opportunity_Id AND
            e.sentTimestamp >=:startDate AND
            e.sentTimestamp <= :finishDate "; 
            
            $query = $app->em->createQuery($dql);
    
            $query->setParameters([
                'opportunity_Id' => $opportunity_id,
                'status' => $status,
                'startDate' => $startDate,
                'finishDate' => $finishDate
            ]);
            $registrations = $query->getResult();
        }else{
            $dql = "SELECT e FROM MapasCulturais\Entities\Registration e
            WHERE e.status = :status AND e.opportunity = :opportunity_Id"; 
            
            $query = $app->em->createQuery($dql);
    
            $query->setParameters([
                'opportunity_Id' => $opportunity_id,
                'status' => $status,
            ]);
            $registrations = $query->getResult();
        }
         //$registrations = $this->filterRegistrations($registrations);   
      
        
        $opp = $opportunities[$opportunity_id];
        
        
        $mapping = [
            'TIPO_INSTRUMENTO' => '1',
            'NUMERO_INSTRUMENTO' => '24',
            'ANO_INSTRUMENTO' => '2020',
            'CPF' => function($registrations) use ($opp, $app){
                $field_temp = $opp['TIPO_PROPONENTE'];
                $field_id = $opp['CPF'];
                if($field_temp){
                    
                    if(trim($registrations->$field_temp) === trim($opp['PESSOA_FISICA']) || trim($registrations->$field_temp) === trim($opp['COLETIVO'])){
                        $result = $this->normalizeString(str_pad($registrations->$field_id, 11, 0));
                        return substr($result, 0, 11);
                    }else{
                        return null;
                    }
                }else{ 
                    $result = $this->normalizeString(str_pad($registrations->$field_id, 11, 0));
                    return substr($result, 0, 11);
                }
                
            },
            'SEXO' => function($registrations) use ($opp, $app, $opportunities){                
                $field_temp = $opp['TIPO_PROPONENTE'];
                $field_id = $opp['SEXO'];
                    if($field_id == 'csvMap'){
                            $filename = PRIVATE_FILES_PATH.'LAB/csv/'. $opp['DIR_CSV'];

                            $stream = fopen($filename, "r");

                            $csv = Reader::createFromStream($stream);

                            $csv->setDelimiter(";");

                            $header_temp = $csv->setHeaderOffset(0);

                            $stmt = (new Statement());
                            $results = $stmt->process($csv);
                                                   
                            foreach($results as $key_a => $a){
                                
                                foreach($a as $key => $b){
                                    
                                    if($a['NUM_INSCRICAO'] === $registrations->number){
                                        
                                        return $this->normalizeString($a['SEXO']);
                                    }
                                }
                            }
                        }else{                            
                            return $this->normalizeString($registrations->$field_id);
                        }
            },
            'CNPJ' => function($registrations) use ($opp, $app){
                $field_temp = $opp['TIPO_PROPONENTE'];
                $field_id = $opp['CNPJ'];
                if($field_temp){
                    if(trim($registrations->$field_temp) == trim($opp['PESSOA_JURIDICA'])){
                        $result = $this->normalizeString(str_pad($registrations->$field_id, 14, 0));
                        return substr($result, 0, 14);
                    }else{
                        return null;
                    }
                }else{
                    //return $this->normalizeString($registrations->$field_id);
                }
            },
            'FLAG_CAD_ESTADUAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_ESTADUAL'] ? 1 : 0;
            },
            'SISTEMA_CAD_ESTADUAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_ESTADUAL'] ? $app->view->dict('site: name', false) : '';

            },
            'IDENTIFICADOR_CAD_ESTADUAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_ESTADUAL'] ?  $registrations->number : '';

            },
            'FLAG_CAD_MUNICIPAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_MUNICIPAL'] ? 1 : 0;
            },
            'SISTEMA_CAD_MUNICIPAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_MUNICIPAL'] ? $app->view->dict('site: name', false) : '';

            },
            'IDENTIFICADOR_CAD_MUNICIPAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_MUNICIPAL'] ? $registrations->number : '';

            },
            'FLAG_CAD_DISTRITAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_DISTRITAL'] ? 1 : 0;

            },
            'SISTEMA_CAD_DISTRITAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_DISTRITAL'] ? $app->view->dict('site: name', false) : '';

            },
            'IDENTIFICADOR_CAD_DISTRITAL' => function($registrations) use ($opp, $app){                    
                return $opp['FLAG_CAD_DISTRITAL'] ? $registrations->number : '';

            },
            'FLAG_CAD_NA_PONTOS_PONTOES' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_CAD_ES_PONTOS_PONTOES' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'SISTEMA_CAD_ES_PONTOS_PONTOES' => function($registrations) use ($opp, $app){                    
                return '';

            },
            'IDENTIFICADOR_CAD_ES_PONTOS_PONTOES' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_CAD_SNIIC' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'SISTEMA_CAD_SNIIC' => function($registrations) use ($opp, $app){                    
                return '';

            },
            'IDENTIFICADOR_CAD_SNIIC' => function($registrations) use ($opp, $app){                    
                return '';

            },
            'FLAG_CAD_SALIC' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_CAD_SICAB' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_CAD_OUTROS' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'SISTEMA_CAD_OUTROS' => function($registrations) use ($opp, $app){                    
                return '';

            },
            'IDENTIFICADOR_CAD_OUTROS' => function($registrations) use ($opp, $app){                    
                return '';

            },
            'FLAG_ATUACAO_ARTES_CENICAS' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_AUDIOVISUAL' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_MUSICA' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_ARTES_VISUAIS' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_PATRIMONIO_CULTURAL' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_MUSEUS_MEMORIA' => function($registrations) use ($opp, $app){                    
                return 0;

            },
            'FLAG_ATUACAO_HUMANIDADES' => function($registrations) use ($opp, $app){                    
                return 0;

            }
        ];
        
        $csv_data = [];
        foreach ($registrations as $key_registration => $registration) {
            foreach ($mapping as $key_mapping => $field) {
                if (is_callable($field)) {
                    $csv_data[$key_registration][$key_mapping] = $field($registration);

                }elseif(is_string($field) && strlen($field) > 0){
                    $csv_data[$key_registration][$key_mapping] = $field;

                }else{
                    $csv_data[$key_registration][$key_mapping] = $field;
                }
            }
   
        }
        
        $file_name = 'inciso3-'.$opportunity_id.'-' . md5(json_encode($csv_data)) . '.csv';

        $dir = PRIVATE_FILES_PATH . 'aldirblanc/inciso3/';

        $patch = $dir . $file_name;

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $stream = fopen($patch, 'w');

        $csv = Writer::createFromStream($stream);

        $csv->insertOne($header);

        foreach ($csv_data as $key_csv => $csv_line) {
            $csv->insertOne($csv_line);
        }

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . $file_name);
        header('Pragma: no-cache');
        readfile($patch);

          
            
        
    }

    public function GET_import() {
        $this->requireAuthentication();

        $app = App::i();

        $opportunity_id = $this->data['opportunity'] ?? 0;
        $file_id = $this->data['file'] ?? 0;

        $opportunity = $app->repo('Opportunity')->find($opportunity_id);

        if (!$opportunity) {
            echo "Opportunidade de id $opportunity_id não encontrada";
        }

        $opportunity->checkPermission('@control');

        $plugin = $app->plugins['AldirBlancDataprev'];

        $config = $app->plugins['AldirBlanc']->config;

        $inciso1_opportunity_id = $config['inciso1_opportunity_id'];
        $inciso2_opportunity_ids = $config['inciso2_opportunity_ids'];

        $files = $opportunity->getFiles('dataprev');
        
        foreach ($files as $file) {
            if ($file->id == $file_id) {
                if($opportunity_id == $inciso1_opportunity_id){
                    $this->import_inciso1($opportunity, $file->getPath());
                } else if (in_array($opportunity_id, $inciso2_opportunity_ids)) {                   
                    $this->import_inciso2($opportunity, $file->getPath());
                }
            }
        }
    }

    function compareNames($n1, $n2) {

    }

    /**
     * Importador para o inciso 1
     *
     * Implementa o sistema de importação dos dados da dataprev para a lei AldirBlanc no inciso 1
     * http://localhost:8080/dataprev/import_inciso1/
     *
     * Parametros to e from não são obrigatórios, caso nao informado retorna os últimos 7 dias de registros
     *
     * Paramentro type se alterna entre cpf e cnpj
     *
     * Paramentro status não é obrigatorio, caso não informado retorna todos com status 1
     *
     */
    public function import_inciso1(Opportunity $opportunity, string $filename)
    {

        /**
         * Seta o timeout e limite de memoria
         */
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');

        // Pega as configurações no arquivo config-csv-inciso1.php
        $conf_csv = $this->config['csv_inciso1'];

        //verifica se o mesmo esta no servidor
        if (!file_exists($filename)) {
            throw new Exception("Erro ao processar o arquivo. Arquivo inexistente");
        }

        $app = App::i();

        //Abre o arquivo em modo de leitura
        $stream = fopen($filename, "r");

        //Faz a leitura do arquivo
        $csv = Reader::createFromStream($stream);

        //Define o limitador do arqivo (, ou ;)
        $csv->setDelimiter(";");

        //Seta em que linha deve se iniciar a leitura
        $header_temp = $csv->setHeaderOffset(0);

        //Faz o processamento dos dados
        $stmt = (new Statement());
        $results = $stmt->process($csv);

        //Verifica a extenção do arquivo
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext != "csv") {
            throw new Exception("Arquivo não permitido.");
        }

        //Verifica se o arquivo esta dentro layout
        foreach ($header_temp as $key => $value) {
            $header_file[] = $value;
            break;

        }

        foreach ($header_file[0] as $key => $value) {
            $header_line_csv[] = $key;

        }

        //Verifica se o layout do arquivo esta nos padroes enviados pela dataprev
        $herder_layout = $conf_csv['herder_layout'];

        if ($error_layout = array_diff_assoc($herder_layout, $header_line_csv)) {            
            throw new Exception("os campos " . json_encode($error_layout) . " estão divergentes do layout necessário.");
        }

        //Inicia a verificação dos dados do requerente
        $evaluation = [];
        $parameters = $conf_csv['acceptance_parameters'];
        
        $registrat_ids = [];

        foreach ($results as $results_key => $item) {
            $registrat_ids[] = $item['IDENTIF_CAD_ESTAD_CULT'];
        }

        $dql = "
        SELECT
            e.number,
            e._agentsData
        FROM
            MapasCulturais\Entities\Registration e
        WHERE
            e.number in (:reg_ids)";

        $query = $app->em->createQuery($dql);
        $query->setParameters([
            'reg_ids' => $registrat_ids
        ]);

        // $agent_names = [];

        // foreach($query->getScalarResult() as $r) {
        //     $data = json_decode($r['_agentsData']);
        //     $agent_names[$r['number']] = $data->owner->nomeCompleto;
        // };
        $raw_data_by_num = [];
        
        $set_monoparental = function(Registration $registration) {
            $raw_data = $registration->dataprev_raw;

            $registration->dataprev_monoparental = strtolower($raw_data->IN_MULH_PROV_MONOPARENT) == 'sim';
            $registration->dataprev_outro_conjuge = strtolower($raw_data->IND_MONOPARENTAL_OUTRO_REQUERIMENTO) == 'sim';
            $registration->dataprev_cpf_outro_conjuge = $raw_data->CPF_OUTRO_REQUERENTE_CONJUGE_INFORMADO;
        };

        // return;
        foreach ($results as $results_key => $result) {
            $raw_data_by_num[$result['IDENTIF_CAD_ESTAD_CULT']] = $result;
            $candidate = $result;
            foreach ($candidate as $key_candidate => $value) {
                if(in_array($key_candidate, $conf_csv['validation_cad_cultural'])) {
                    continue;
                }

                // se for um dos campos de identificação de mulher provedora monoparental, continua
                if(in_array($key_candidate, ['IN_MULH_PROV_MONOPARENT', 'IND_MONOPARENTAL_OUTRO_REQUERIMENTO', 'CPF_OUTRO_REQUERENTE_CONJUGE_INFORMADO'])) {
                    continue;
                }

                if ($key_candidate == 'IDENTIF_CAD_ESTAD_CULT') {
                    $evaluation[$results_key]['DADOS_DO_REQUERENTE']['N_INSCRICAO'] = $value;
                }

                if ($key_candidate == 'REQUERENTE_CPF') {
                    $evaluation[$results_key]['DADOS_DO_REQUERENTE']['CPF'] = $value;
                }

                $field = isset($parameters[$key_candidate]) ? $parameters[$key_candidate] : "";
                
                if (is_array($field)) {

                    if ($key_candidate == "REQUERENTE_DATA_NASCIMENTO") {
                        $date = explode("/", $value);
                        $date = new DateTime($date[2] . '-' . $date[1] . '-' . $date[0]);
                        $idade = $date->diff(new DateTime(date('Y-m-d')));

                        if ($idade->format('%Y') >= $field['positive'][0]) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;

                        } else {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = $field['response'];
                        }

                    }elseif ($key_candidate == "SITUACAO_CADASTRO") {

                        if (in_array(trim($value), $field['positive'])) {
                            $evaluation[$results_key]['VALIDATION']['SITUACAO_CADASTRO'] = true;

                        } elseif (in_array(trim($value), $field['negative'])) {
                            if(is_array($field['response'])){
                                $evaluation[$results_key]['VALIDATION']['SITUACAO_CADASTRO'] = $field['response'][$value];

                            }else{
                                $evaluation[$results_key]['VALIDATION']['SITUACAO_CADASTRO'] = $field['response'];
                                
                            }
                            

                        }

                    // A validação de cadastro cultural não é necessária pq o mapas é um cadastro válido 
                    // }elseif (in_array($key_candidate,  $conf_csv['validation_cad_cultural'] )){
                        
                    //     if (in_array(trim($value), $field['positive'])) {
                    //         $evaluation[$results_key]['VALIDATION']['VALIDA_CAD_CULTURAL'] = true;

                    //     } elseif (in_array(trim($value), $field['negative'])) {
                    //         $evaluation[$results_key]['VALIDATION']['VALIDA_CAD_CULTURAL'] = $field['response'];

                    //     }
                      
                    }else {

                        if (in_array(trim($value), $field['positive'])) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;

                        } elseif (in_array(trim($value), $field['negative'])) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = $field['response'];

                        }

                    }

                } else {

                    if ($field) {
                        if ($value === $field) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
                        }

                    }

                }

            }

        }
        
        //Define se o requerente esta apto ou inapto levando em consideração as diretrizes de negocio
        $result_aptUnfit = [];       
        foreach ($evaluation as $key_evaluetion => $value) {
            $result_validation = array_diff($value['VALIDATION'], $conf_csv['validation_reference']);
            if (!$result_validation) {
                $result_aptUnfit[$key_evaluetion] = $value['DADOS_DO_REQUERENTE'];
                $result_aptUnfit[$key_evaluetion]['ACCEPT'] = true;
            } else {
                $result_aptUnfit[$key_evaluetion] = $value['DADOS_DO_REQUERENTE'];                
                $result_aptUnfit[$key_evaluetion]['ACCEPT'] = false;
                foreach ($value['VALIDATION'] as $value) {
                    if (is_string($value)) {
                        $result_aptUnfit[$key_evaluetion]['REASONS'][] = $value;
                    }
                }
            }

        }
        $aprovados = array_values(array_filter($result_aptUnfit, function($item) {
            if($item['ACCEPT']) {
                return $item;
            }
        }));

        $reprovados = array_values(array_filter($result_aptUnfit, function($item) {
            if(!$item['ACCEPT']) {
                return $item;
            }
        }));

        $app->disableAccessControl();
        $count = 0;
        
        foreach($aprovados as $r) {
            $count++;
            
            $registration = $app->repo('Registration')->findOneBy(['number' => $r['N_INSCRICAO']]);
            if (!$registration){
                continue;
            }
            $registration->__skipQueuingPCacheRecreation = true;
            
            /* @TODO: implementar atualização de status?? */
            if ($registration->dataprev_raw != (object) []) {
                $app->log->info("Dataprev #{$count} {$registration} APROVADA - JÁ PROCESSADA");
                continue;
            }
            
            $app->log->info("Dataprev #{$count} {$registration} APROVADA");
            
            $registration->dataprev_raw = (object) $raw_data_by_num[$registration->number];
            $registration->dataprev_processed = $r;
            $registration->dataprev_filename = $filename;

            $set_monoparental($registration);

            $registration->save(true);
    
            $user = $app->plugins['AldirBlancDataprev']->getUser();

            /* @TODO: versão para avaliação documental */
            $evaluation = new RegistrationEvaluation;
            $evaluation->__skipQueuingPCacheRecreation = true;
            $evaluation->user = $user;
            $evaluation->registration = $registration;
            $evaluation->evaluationData = ['status' => "10", "obs" => 'selecionada'];
            $evaluation->result = "10";
            $evaluation->status = 1;

            $evaluation->save(true);

            $app->em->clear();

        }

        foreach($reprovados as $r) {
            $count++;

            $registration = $app->repo('Registration')->findOneBy(['number' => $r['N_INSCRICAO']]);
            if (!$registration){
                continue;
            }
            $registration->__skipQueuingPCacheRecreation = true;
            
            if ($registration->dataprev_raw != (object) []) {
                $app->log->info("Dataprev #{$count} {$registration} REPROVADA - JÁ PROCESSADA");
                continue;
            }

            $app->log->info("Dataprev #{$count} {$registration} REPROVADA");

            $registration->dataprev_raw = (object) $raw_data_by_num[$registration->number];
            $registration->dataprev_processed = $r;
            $registration->dataprev_filename = $filename;

            $set_monoparental($registration);

            $registration->save(true);

            $user = $app->plugins['AldirBlancDataprev']->getUser();

            /* @TODO: versão para avaliação documental */
            $evaluation = new RegistrationEvaluation;
            $evaluation->__skipQueuingPCacheRecreation = true;
            $evaluation->user = $user;
            $evaluation->registration = $registration;
            $evaluation->evaluationData = ['status' => "2", "obs" => implode("\\n", $r['REASONS'])];
            $evaluation->result = "2";
            $evaluation->status = 1;

            $evaluation->save(true); 

            $app->em->clear();

        }
        
        // por causa do $app->em->clear(); não é possível mais utilizar a entidade para salvar
        $opportunity = $app->repo('Opportunity')->find($opportunity->id);
        
        $opportunity->refresh();
        $opportunity->name = $opportunity->name . ' ';
        $files = $opportunity->dataprev_processed_files;
        $files->{basename($filename)} = date('d/m/Y \à\s H:i');
        $opportunity->dataprev_processed_files = $files;
        $opportunity->save(true);
        $app->enableAccessControl();
        $this->finish('ok');
    }

    public function import_inciso2(Opportunity $opportunity, string $filename) { 
        
        /**
         * Seta o timeout e limite de memoria
         */
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');

        // Pega as configurações no arquivo config-csv-inciso1.php
        $conf_csv = $this->config['csv_inciso2'];       

        //verifica se o mesmo esta no servidor
        if (!file_exists($filename)) {
            throw new Exception("Erro ao processar o arquivo. Arquivo inexistente");
        }

        $app = App::i();

        //Abre o arquivo em modo de leitura
        $stream = fopen($filename, "r");

        //Faz a leitura do arquivo
        $csv = Reader::createFromStream($stream);

        //Define o limitador do arqivo (, ou ;)
        $csv->setDelimiter(";");

        //Seta em que linha deve se iniciar a leitura
        $header_temp = $csv->setHeaderOffset(0);

        //Faz o processamento dos dados
        $stmt = (new Statement());
        $results = $stmt->process($csv);

        //Verifica a extenção do arquivo
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext != "csv") {
            throw new Exception("Arquivo não permitido.");
        }

        //Verifica se o arquivo esta dentro layout
        foreach ($header_temp as $key => $value) {
            $header_file[] = $value;
            break;
        }

        foreach ($header_file[0] as $key => $value) {
            $header_line_csv[] = $key;
        }
        
        //Verifica se o layout do arquivo esta nos padroes enviados pela dataprev
        $herder_layout = $conf_csv['herder_layout'];
        
        if ($error_layout = array_diff_assoc($herder_layout, $header_line_csv)) {            
            throw new Exception("os campos " . json_encode($error_layout) . " estão divergentes do layout necessário.");

        } 
        
        //Inicia a verificação dos dados do requerente
        $evaluation = [];
        $parameters = $conf_csv['acceptance_parameters'];
        $register = $conf_csv['RegisterNumber'];
        
        $registrat_ids = [];        

        foreach ($results as $results_key => $item) {
            
            $registrat_ids[] = $item[$register];
        }       
        
        $dql = "
        SELECT
            e.number,
            e._agentsData
        FROM
            MapasCulturais\Entities\Registration e
        WHERE
            e.number in (:reg_ids)";

        $query = $app->em->createQuery($dql);
        $query->setParameters([
            'reg_ids' => $registrat_ids
        ]);

        // $agent_names = [];

        // foreach($query->getScalarResult() as $r) {
        //     $data = json_decode($r['_agentsData']);
        //     $agent_names[$r['number']] = $data->owner->nomeCompleto;
        // };
       
        $raw_data_by_num = [];
        // return;
        foreach ($results as $results_key => $result) {
            
            $raw_data_by_num[$result[$register]] = $result;

            $candidate = $result;
            foreach ($candidate as $key_candidate => $value) {                
                
                if ($key_candidate == $conf_csv['RegisterNumber']) {
                    $evaluation[$results_key]['DADOS_DO_REQUERENTE']['N_INSCRICAO'] = $value;
                }

                if ($key_candidate == 'REQUERENTE_CPF' && !empty($value)) {
                    $evaluation[$results_key]['DADOS_DO_REQUERENTE']['CPF'] = $value;
                }elseif($key_candidate == 'REQUERENTE_CNPJ' && !empty($value)){
                    $evaluation[$results_key]['DADOS_DO_REQUERENTE']['CNPJ'] = $value;
                }

                $field = isset($parameters[$key_candidate]) ? $parameters[$key_candidate] : "";
                
                if (is_array($field)) { 
                    if(!empty($field)){
                        if ($key_candidate == "REQUERENTE_DATA_NASCIMENTO") {
                            if(!empty($value)){
                                $date = explode("/", $value);
                                $date = new DateTime($date[2] . '-' . $date[1] . '-' . $date[0]);
                                $idade = $date->diff(new DateTime(date('Y-m-d')));
        
                                if ($idade->format('%Y') >= $field['positive'][0]) {
                                    $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
        
                                } else {
                                    $evaluation[$results_key]['VALIDATION'][$key_candidate] = $field['response'];
                                }
                            }else{
                                $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
                            }
                          
    
                        }elseif ($key_candidate == "NATUREZA_JURIDICA") {
                           if(empty($value)){
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
                           }else{
                            if(in_array($this->normalizeString($value), $field['positive'])) {
                                $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
                            }else{
                                $evaluation[$results_key]['VALIDATION'][$key_candidate] = $field['response'];
                            }
                           }
                          
                        }elseif (in_array(trim($value), $field['positive'])) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;

                        } elseif (in_array(trim($value), $field['negative'])) {
                            $evaluation[$results_key]['VALIDATION'][$key_candidate] = $field['response'];

                        }
                    }
                
                }else {
                    if ($field) {
                        if ($value === $field) {
                        $evaluation[$results_key]['VALIDATION'][$key_candidate] = true;
                    }
                }
            }
        }
        }


        //Define se o requerente esta apto ou inapto levando em consideração as diretrizes de negocio
        $result_aptUnfit = [];       
        foreach ($evaluation as $key_evaluetion => $value) {
            $result_validation = array_diff($value['VALIDATION'], $conf_csv['validation_reference']);
            if (!$result_validation) {
                $result_aptUnfit[$key_evaluetion] = $value['DADOS_DO_REQUERENTE'];
                $result_aptUnfit[$key_evaluetion]['ACCEPT'] = true;
            } else {
                $result_aptUnfit[$key_evaluetion] = $value['DADOS_DO_REQUERENTE'];                
                $result_aptUnfit[$key_evaluetion]['ACCEPT'] = false;
                foreach ($value['VALIDATION'] as $value) {
                    if (is_string($value)) {
                        $result_aptUnfit[$key_evaluetion]['REASONS'][] = $value;
                    }
                }
            }

        }
       
        $aprovados = array_values(array_filter($result_aptUnfit, function($item) {
            if($item['ACCEPT']) {
                return $item;
            }
        }));

        $reprovados = array_values(array_filter($result_aptUnfit, function($item) {
            if(!$item['ACCEPT']) {
                return $item;
            }
        }));

        $app->disableAccessControl();
        $count = 0;
        
        foreach($aprovados as $r) {
            $count++;
            
            $registration = $app->repo('Registration')->findOneBy(['number' => $r['N_INSCRICAO']]);
            if (!$registration){
                continue;
            }
            $registration->__skipQueuingPCacheRecreation = true;
            
            /* @TODO: implementar atualização de status?? */
            if ($registration->dataprev_raw != (object) []) {
                $app->log->info("Dataprev #{$count} {$registration} APROVADA - JÁ PROCESSADA");
                continue;
            }
            
            $app->log->info("Dataprev #{$count} {$registration} APROVADA");
            
            $registration->dataprev_raw = $raw_data_by_num[$registration->number];
            $registration->dataprev_processed = $r;
            $registration->dataprev_filename = $filename;
            $registration->save(true);
    
            $user = $app->plugins['AldirBlancDataprev']->getUser();

            /* @TODO: versão para avaliação documental */
            $evaluation = new RegistrationEvaluation;
            $evaluation->__skipQueuingPCacheRecreation = true;
            $evaluation->user = $user;
            $evaluation->registration = $registration;
            $evaluation->evaluationData = ['status' => "10", "obs" => 'selecionada'];
            $evaluation->result = "10";
            $evaluation->status = 1;

            $evaluation->save(true);

            $app->em->clear();

        }

        foreach($reprovados as $r) {
            $count++;

            $registration = $app->repo('Registration')->findOneBy(['number' => $r['N_INSCRICAO']]);
            if (!$registration){
                continue;
            }
            $registration->__skipQueuingPCacheRecreation = true;
            
            if ($registration->dataprev_raw != (object) []) {
                $app->log->info("Dataprev #{$count} {$registration} REPROVADA - JÁ PROCESSADA");
                continue;
            }

            $app->log->info("Dataprev #{$count} {$registration} REPROVADA");

            $registration->dataprev_raw = $raw_data_by_num[$registration->number];
            $registration->dataprev_processed = $r;
            $registration->dataprev_filename = $filename;
            $registration->save(true);

            $user = $app->plugins['AldirBlancDataprev']->getUser();

            /* @TODO: versão para avaliação documental */
            $evaluation = new RegistrationEvaluation;
            $evaluation->__skipQueuingPCacheRecreation = true;
            $evaluation->user = $user;
            $evaluation->registration = $registration;
            $evaluation->evaluationData = ['status' => "2", "obs" => implode("\\n", $r['REASONS'])];
            $evaluation->result = "2";
            $evaluation->status = 1;

            $evaluation->save(true); 

            $app->em->clear();

        }
        
        // por causa do $app->em->clear(); não é possível mais utilizar a entidade para salvar
        $opportunity = $app->repo('Opportunity')->find($opportunity->id);
        
        $opportunity->refresh();
        $opportunity->name = $opportunity->name . ' ';
        $files = $opportunity->dataprev_processed_files;
        $files->{basename($filename)} = date('d/m/Y \à\s H:i');
        $opportunity->dataprev_processed_files = $files;
        $opportunity->save(true);
        $app->enableAccessControl();
        $this->finish('ok');        
        
    }

    /**
     * Faz a normalização da string e remove caracteres especiais
     *
     * @param string $value
     * @return string
     */
    private function normalizeString($value): string
    {
        $value = Normalizer::normalize($value, Normalizer::FORM_D);
       return preg_replace('/[^a-z0-9 ]/i', '', $value);
    }

    /**
    * Fix para inscrições com flag monoparental 
    *
    * rota: /dataprev/fiximportmono
    *
    * @return void
    */
    function GET_fiximportmono()
    {
        $this->requireAuthentication();
        $app = App::i();       
        if (!$app->user->is('admin')){
            return;
        }
        $plugin = $app->plugins['AldirBlancDataprev'];
        $user_dataprev = $plugin->getUser();

        // pegar inscrições com alguma flag monoparenta = sim
        $dql = "SELECT rm, r from MapasCulturais\\Entities\\RegistrationMeta rm 
        JOIN rm.owner r
        WHERE rm.key = 'dataprev_raw'
        AND rm.value like '%\"IN_MULH_PROV_MONOPARENT\":\"Sim\"%'
        OR  rm.value like '%\"IND_MONOPARENTAL_OUTRO_REQUERIMENTO\":\"Sim\"%'";
        $query = $app->em->createQuery($dql);
        $registrationsMeta = $query->getResult();
        $haystack = 'No preenchimento do Formulário de Inscrição, o requerente não atendeu ao § 2º do Art. 6º da Lei 14.017/2020 e ao Inciso II do Art. 3º do Decreto nº 10.464/2020.';
        foreach ( $registrationsMeta as $rm ){
            $evaluation = $app->repo('RegistrationEvaluation')->findOneBy(['registration' => $rm->owner, 'user' => $user_dataprev]);
            if ($evaluation->evaluationData->obs == $haystack){
                $evaluation->result = '10';
                $evaluationData = $evaluation->evaluationData;
                $evaluationData->status = '10';
                $evaluationData->obs = 'Selecionada';
                $evaluation->evaluationData = $evaluationData;
                $evaluation->save(true);
                $app->log->info('Avaliação para ' . $rm->owner . 'alterada para '. $evaluation->result); 
                $app->log->info('evaluation_data para ' . $rm->owner . 'alterada para '. $evaluation->evaluationData->obs); 

            } 
            else if(strpos($evaluation->evaluationData->obs, $haystack) !== false ){
                $evaluationData = $evaluation->evaluationData;
                $evaluationData->obs = str_replace (
                    $haystack,
                    '',
                    $evaluation->evaluationData->obs
                );
                $evaluation->evaluationData = $evaluationData;
                $evaluation->save(true);
                $app->log->info('evaluation_data para ' . $rm->owner . 'alterada para '. $evaluation->evaluationData->obs); 
            }
            if (is_object($rm->owner->dataprev_raw)){
                $rm->owner->dataprev_monoparental = strtolower($rm->owner->dataprev_raw->IN_MULH_PROV_MONOPARENT) == 'sim';
                $rm->owner->dataprev_outro_conjuge = strtolower($rm->owner->dataprev_raw->IND_MONOPARENTAL_OUTRO_REQUERIMENTO) == 'sim';
                $rm->owner->dataprev_cpf_outro_conjuge = $rm->owner->dataprev_raw->CPF_OUTRO_REQUERENTE_CONJUGE_INFORMADO;
                $app->disableAccessControl();
                $rm->owner->save(true);       
                $app->enableAccessControl();
            }
            
        }
    }
}
