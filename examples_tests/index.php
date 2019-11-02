<?php

require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use Replication\CreateDestinationTable\Config;

$config = new Config();
$source_table = $config->getSourceTableName();
$db = $config->getSourceTableConnect();
$dest_table = $config->getDestTableName();
$repl_history = $config->getReplHistoryTableName();

?><style>
    .flex{
        display:flex;
    }
</style>

<h2>Репликатор БД SQL</h2>
<h3>Выберите действие:</h3>
<select name="variant" id="selector">
    <option selected value="testCreate.php">1 Создать таблицу репликанта и таблицу истории репликации</option>
    <option value="testReplicate.php">2 Реплицировать таблицу из оригинала в репликанта</option>
    <option value="testReplicateByAPI.php">3 Реплицировать таблицу по API</option>
</select>

<script>

</script>
<div class="flex">
    <form action="testCreate.php" class="form" name="defaultForm">
        <h4>Использовать настройки по умолчанию:</h4>
        <p>db_host: localhost</p>
        <p>db_name: repl</p>
        <p>db_user: root</p>
        <p>db_pass: ''</p>
        <p>Название таблицы оригинала: <?= $source_table ?></p>
        <p>Название таблицы репликанта: <?= $dest_table ?></p>
        <p>Название таблицы с историей репликации: <?= $repl_history ?></p>
        <input type="submit" value="submit">
    </form>
    <form action="testCreate.php" style="display: flex;
    flex-wrap: wrap;
    flex-direction: column;
    width:305px;
" class="form" name="customForm">
        <h4>Либо задать свои настройки:</h4>
        <input type="hidden" value="true" name="new_config">

        <p>Настройки БД таблицы оригинала</p>
        <input name="source_DB_CONNECTION" type="text" placeholder="source_db_connection" value="mysql">
        <input name="source_DB_HOST" type="text" placeholder="source_db_host" value="localhost">
        <input name="source_DB_PORT" type="number" placeholder="source_db_port" value="3306">
        <input name="source_DB_DATABASE" type="text" placeholder="source_db_name" value="repl">
        <input name="source_DB_USERNAME" type="text" placeholder="source_db_user" value="root">
        <input name="source_DB_PASSWORD" type="password" placeholder="source_db_pass" value="">
        <input name="source_DB_TABLE" type="text" placeholder="source_table" value="<?= $source_table ?>">

        <p>Настройки БД таблицы репликанта <span>(используется так же для репликации в API)</span></p>
        <label>dest type connection:</label>
        <input name="dest_DB_CONNECTION" type="text" placeholder="source_db_connection" value="mysql">
        <label>dest_db_host:</label>
        <input name="dest_DB_HOST" type="text" placeholder="dest_db_host" value="localhost">
        <label>dest_db_port:</label>
        <input name="dest_DB_PORT" type="number" placeholder="dest_db_port" value="3306">
        <label>dest_db_name:</label>
        <input name="dest_DB_DATABASE" type="text" placeholder="dest_db_name" value="repl">
        <label>dest_db_user:</label>
        <input name="dest_DB_USERNAME" type="text" placeholder="dest_db_user" value="root">
        <label>dest_db_pass:</label>
        <input name="dest_DB_PASSWORD" type="password" placeholder="dest_db_pass" value="">
        <label>Название новой реплицированной таблицы:</label>
        <input name="dest_DB_TABLE" type="text" placeholder="dest_table" value="<?= $dest_table ?>">
        <label>Название таблицы с историей репликации:</label>
        <input name="repl_history" type="text" placeholder="repl_history" value="<?= $repl_history ?>">
        <input type="submit" value="submit">
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let selector = document.getElementById("selector");
        let currentTargetAction = selector.options[selector.selectedIndex].value;
        setToAllForms(currentTargetAction);
    });
    document.querySelector("#selector").onchange = function (e) {
        setToAllForms(this.value);
    };
    function setToAllForms(action){
        let forms = document.getElementsByClassName('form');
        for (let form of forms){
            form.action = action;
            console.log(form.name + " action changed to " + action);
        }
    }
</script>