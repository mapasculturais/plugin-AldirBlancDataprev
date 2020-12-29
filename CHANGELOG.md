
## [Unreleased]
- Adiciona MIME type enviado pelo Windows ao file group dos retornos do Dataprev (Ref. [#18](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/18))
- Corrige importador Dataprev para reprocessar inscrições que em um primeiro momento estava, com staus 8 "Retida para avaliação" (Ref. [#16](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/16))
- Corrige erro da busca da coluna situação de cadastro (Ref. [#20](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/20))
- Corrige procfesso de reprocessamento na importação do Dataprev (Ref. [#19](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/19))

## [1.0.2]
- Corrige exportador do inciso 1 substituindo os CPFs inválidos dos familiares por CPFs válidos que não constam na base da Receita Federal

## [1.0.1] - 2020-11-24
- Aplica correção nos exportador Dataprev, para garantir que no seja inserido caracteres especiais no nome do espaço cultural (Ref. [#17](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/17))
- Aplica correção nos exportadores Dataprev para que permita somente número em campos de CPF e CNPJ (Ref. [#17](https://github.com/mapasculturais/plugin-AldirBlancDataprev/issues/17))

## [1.0.0] - 2020-11-24
- Aplica no inciso 2 a mesma correção já feita para o inciso 1 (não verificando as razões de anulamento para as aprovadas).
- Adiciona CHANGELOG.md
