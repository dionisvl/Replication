<?php

namespace Replication\ReplicateTable;

use PDO;

/**
 * Это полноценная репликация с заполнением таблицы истории и служебных колонок в репликанте
 *
 * Скопируем всё из таблицы из БД оригинала в таблицу БД назначения
 * - алгоритм учитывает последний копированный id и последний копированный timestamp
 * для избежания повторного копирования
 * - информация о результатах копирования заносится в таблицу истории репликации
 * - размер копируемой пачки ограничивается из параметров ini файла.
 *
 * Class ReplicateTable
 */
class ReplicateTable
{
    private $config;
    private $start_time;
    private $end_time;

    private $sourceTableColumns = [];
    private $sourceTableColumnsString = '';
    private $ODKU_textBlock = '';

    public function __construct(ReplicateTableConfig $config)
    {
        $this->start_time = microtime(true);

        $this->config = $config;

        /**Если процесс копирования завершен, тогда установим статус о том что он начался
         * IN_PROCESS - в процессе. FREE - процесс копирования завершен и готов начаться снова.
         */
        if ($this->config->getReplicateProcessStatus() == 'FREE') {
            $this->config->setReplicateProcessStatus('IN_PROCESS');
        } else {
            print_r('Процесс репликации уже запущен. Поэтому ваш запрос отклонен и программа завершает работу.' . PHP_EOL);
            die();
        }
//        $this->repl_history = $this->config->getReplHistoryTableName();

        $this->sourceTableColumns = $this->getTableColumns(
            $this->config->getSourceTableConnect(),
            $this->config->getSourceTableName()
        );

        /**сформируем строчный список колонок исходной таблицы
         *  Образец:
         *      id, name, phone, user_status, create_ts, update_ts
         */
        $this->sourceTableColumnsString = implode(', ', $this->sourceTableColumns);
        $this->ODKU_textBlock = $this->createOnDuplicateKeyUpdateTextBlock($this->sourceTableColumns);
        /**
         * - Сначала создаем запись о начале репликации в таблице repl_history
         * - извлекаем её last_insert_id в $id
         * - запускаем процесс репликации
         * ...
         * - в процессе репликации сохраняем в таблице dest_table в поле repl_proc_id $id
         * ...
         * - по завершении репликации заполняем поле repl_create_ts в табл  repl_history
         */

        print_r('Ограничительный размер пачки репликации - $batch_size: ' . $this->config->getBatchSize() . PHP_EOL . '<br>');
        print_r('Последний обработанный timestamp: $last_processed_ts: ' . $this->config->getLastProcessedTs() . PHP_EOL . '<br>');
        print_r('Последний обработанный id, $last_processed_id: ' . $this->config->getLastProcessedId() . PHP_EOL . '<br>');


        $this->get_batch(
            $this->config->getSourceTableConnect(),
            $this->config->getDestTableConnect(),
            $this->config->getLastProcessedTs(),
            $this->config->getLastProcessedId(),
            $this->config->getBatchSize()
        );
        $this->config->setReplicateProcessStatus('FREE');//Установим флаг о том что процесс завершился
        dump("<h3>Репликация всех пачек завершена</h3>");
        $this->end_time = microtime(true);
    }

    /**
     * Если $id не передан, тогда создадим новую запись в истории о том что репликация началась иначе
     * изменим запись в таблице о том что репликация пачки завершилась
     */
    private function setReplicateHistoryRecord($db, $batch_size, $update_ts_first, $update_ts_last, $status, $info, $id = null)
    {
        $repl_history = $this->config->getReplHistoryTableName();
        try {
            if ($id) {
                $sql = "
                    UPDATE $repl_history 
                       SET         
                        batch_size = :batch_size
                        ,update_ts_first = :update_ts_first
                        ,update_ts_last = :update_ts_last
                        ,status = :status
                        ,info = :info
                    WHERE id = :id
                 ";
            } else {
                $sql = "
                    INSERT INTO $repl_history (batch_size, update_ts_first, update_ts_last , status, info)
                    VALUES (:batch_size, :update_ts_first,:update_ts_last, :status, :info)
                 ";
            }

            $sth = $db->prepare($sql);
            $sth->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
            $sth->bindValue(':update_ts_first', $update_ts_first);
            $sth->bindValue(':update_ts_last', $update_ts_last);
            $sth->bindValue(':status', $status, PDO::PARAM_INT);
            $sth->bindValue(':info', $info);
            if ($id) {
                $sth->bindValue(':id', $id, PDO::PARAM_INT);
            }
            $sth->execute();
            $this->check_pdo($sth);
            if ($id) {
                return $id;
            } else
                return $db->lastInsertId();
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setReplicateProcessStatus('FREE');//Установим флаг о том что процесс завершился
            die();
        }
    }

    //Получим все колонки таблицы
    private function getTableColumns($db, $tableName)
    {
        $a = [];
        $sql = "SHOW COLUMNS FROM `$tableName`";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $this->check_pdo($stmt);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $a[] = $row['Field'];
        }

        return $a;
    }

    /**
     * Сформируем тесктовый блок для запроса. Образец:
     * id = src.id
     * , name = src.name
     * , phone = src.phone
     * , user_status = src.user_status
     * , create_ts = src.create_ts
     * , update_ts = src.update_ts
     * , repl_proc_id = src.repl_proc_id
     * @return string
     */
    private function createOnDuplicateKeyUpdateTextBlock($tableColumns)
    {
        $resultText = '';
        foreach ($tableColumns as $column) {
            $resultText .= "$column = src.$column" . PHP_EOL . "    , ";
        }
        $resultText .= 'repl_proc_id = src.repl_proc_id' . PHP_EOL;

        return $resultText;
    }


    /**
     * Метод составляет множественный блок UNION SELECT текста для последующей вставки в запрос
     *
     * Образцовый пример того как должен выглядеть результат метода:
     *
     * SELECT
     * 0 as id
     * , NULL as col_A
     * , NULL as col_B
     * , ...
     * , NULL as create_ts
     * , NULL as update_ts
     * , NULL as repl_proc_id
     * UNION SELECT ..., ..., ..., ::repl_proc_id
     * UNION SELECT ..., ..., ..., ::repl_proc_id
     * @return string
     */
    private function createUnionSelect($data, $tableColumns, $repl_proc_id = null)
    {
        $union_text = '';

        foreach ($data as $key => $row) {
            if ($key != 0) {
                $union_text .= '    UNION ';
            }
            $union_text .= 'SELECT' . PHP_EOL . '        ';

            foreach ($tableColumns as $column) {
                $union_text .= "'$row[$column]' as `$column`" . PHP_EOL . "        , ";
            }

            if ($repl_proc_id) {
                $union_text .= "'$repl_proc_id' as repl_proc_id" . PHP_EOL;
            }
        }

        return $union_text;
    }

    /**
     * Пакетная выборка записей.
     * Берем из БД ограниченное кол-во параметром "batch_size"
     *
     * Поле update_ts исходной таблицы обновляется каждый раз при изменении записи.
     *
     * Отдельное объяснение только по параметру ::last_processed_id.
     * Он вводится потому, что с одним и тем же граничным update_ts может существовать несколько записей, и из-за
     * ограничения размером пакета некоторые из них могут не попасть в текущий получаемый пакет.
     * Из-за этого же в выражении update_ts >= ::last_processed_ts стоит нестрогое неравенство.
     *
     * После получения пакета репликационный скрипт должен сохранить максимальное значение update_ts из всех полученных
     * записей и максимальное значение id из всех записей с максимальным update_ts.
     */

    private function get_batch($db, $dest_db, $last_processed_ts, $last_processed_id, int $batch_size)
    {
        $source_table = $this->config->getSourceTableName();
        $dest_table = $this->config->getDestTableName();

        try {
            static $iteration_number;
            $iteration_number++;
            print_r("<h3>Началась репликация пачки № $iteration_number.</h3>");
            $sql = "
SELECT * from $source_table
where TRUE
    and update_ts >= :last_processed_ts
    and id > IF(update_ts = :last_processed_ts, :last_processed_id, 0)
order by update_ts, id
limit :batch_size;
";
            //получим пакет записей из родительской таблицы
            $sth = $db->prepare($sql);
            $sth->bindValue(':last_processed_ts', $last_processed_ts, PDO::PARAM_STR);
            $sth->bindValue(':last_processed_id', $last_processed_id, PDO::PARAM_INT);
            $sth->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
            $sth->execute();
            $this->check_pdo($sth);

            $data = $sth->fetchAll();
            if (empty($data)) {
//                dump($data);
                dump('<h3>Свежих записей не обнаружено!</h3>');
                return false;
            }


            //добавим в таблицу repl_history запись о том что репликация началась для этого пакета записей и получим айди этой записи
            $repl_proc_id = $this->setReplicateHistoryRecord($dest_db, $batch_size, null, null, 0, 'Репликация начата');

            $union_text = $this->createUnionSelect($data, $this->sourceTableColumns, $repl_proc_id);

//        dump($union_text);
//$this->config->setReplicateProcessStatus('FREE');//Установим флаг о том что процесс завершился
//        die();
            /**
             * Для репликации необходимо использовать mysql-инструкцию insert ... select ... on duplicate key update.
             * Запрос подобного вида выполняется репликационным скриптом один раз после получения пакета записей из исходной таблицы.
             */
            $sql = "
INSERT INTO $dest_table ($this->sourceTableColumnsString, repl_proc_id)
SELECT * FROM (
    $union_text
) AS src
WHERE src.id > 0
ON DUPLICATE KEY UPDATE 
    $this->ODKU_textBlock
";

            $sth = $dest_db->prepare($sql);
            $sth->execute();
            $this->check_pdo($sth);

            $max_id = 0;
            $max_ts = $last_processed_ts;
            $this_batch_size = 0;
            foreach ($data as $row) {
                if ($row['update_ts'] > $max_ts) {
                    $max_ts = $row['update_ts'];
                    $max_id = $row['id'];
                } else {
                    if ($row['update_ts'] == $max_ts) {
                        if ($row['id'] > $max_id) {
                            $max_id = $row['id'];
                        }
                    }
                }
                foreach ($row as $key => $value1) {
                    print_r($key . ' - ' . $value1 . ' <br> ');
                }
                $this_batch_size++;
            }
            $update_ts_last = $max_ts;
            $update_ts_first = $data[array_key_first($data)]['update_ts'];
            $repl_status = $this->setReplicateHistoryRecord($dest_db, $this_batch_size, $update_ts_first, $update_ts_last, 1, 'Репликация завершена', $repl_proc_id);

            dump(PHP_EOL . "<h4>Репликация пачки завершена. ID Записи в истории репликации: $repl_status. </h4>");
            dump("<p>Размер этой обновленной пачки: $this_batch_size. </p>");
            dump("<p>Последний обновленный ID: $max_id. </p>");
            dump("<p>Последний обновленный ts: $max_ts. </p><br><br>");

            $this->config->setLastProcessedId($max_id);
            $this->config->setLastProcessedTs($max_ts);
            /**
             * Если количество записей в реплицированной пачке = максимальному размеру пачки
             * и максимальные обработанные TS и ID больше чем у предыдущей обработанной пачки
             * Тогда запустим обработку следующей пачки
             */
            if ($this_batch_size == $batch_size) {
                if (($max_ts >= $last_processed_ts) AND ($max_id > $last_processed_id)) {
                    $last_processed_ts = $max_ts;
                    $last_processed_id = $max_id;

                    $this->get_batch($db, $dest_db, $last_processed_ts, $last_processed_id, $batch_size);
                }
            }
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setReplicateProcessStatus('FREE');//Установим флаг о том что процесс завершился
            die();
        }
    }

    public function getExecutionTime()
    {
        return number_format($this->end_time - $this->start_time, 3, '.', ',');
    }

    private function check_pdo($sth)
    {
        if (!empty($sth->errorInfo()[2])) {
            dump('['.__LINE__.']Произошла ошибка репликации:');
            dump($sth->errorInfo()[2]);
            dump('Полная структура запроса:');
            dump($sth);
            $this->config->setReplicateProcessStatus('FREE');//Установим флаг о том что процесс завершился
            $backtrace = debug_backtrace();
            dump($backtrace);
            die();
        }
    }
}