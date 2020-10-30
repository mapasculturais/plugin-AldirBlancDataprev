
<div style="margin-bottom: 1em; text-align: right;">
    <div style="text-align: left; display: inline-block;">
        <?php
        if ($plugin->config['inciso1_enabled']) {
            $this->part('aldirblanc/csv-button', ['inciso' => 1, 'project' => $project->id, 'plugin_validador' => $plugin_validador]);
        }

        if ($plugin->config['inciso2_enabled']) {
            $this->part('aldirblanc/csv-button', ['inciso' => 2, 'project' => $project->id, 'plugin_validador' => $plugin_validador]);
        }

        /**
         * @todo: implementar para o inciso 3
         */
        // if ($plugin->config['inciso3_enabled']) {
        //     $this->part('aldirblanc/csv-button', ['inciso' => 3, 'project' => $project->id, 'plugin_validador' => $plugin_validador]);
        // }
        ?>
    </div>
</div>