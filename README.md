# SQL table replication

Репликация таблицы из одной базы данных в другую  
состоит из 3-х компонентов:
   - алгоритм проверки и создания таблицы если она не существует с полным повторением типов данных колонок оригинала.
   - алгоритм копирования всех данных из одной таблицы в другую
   - алгоритм копирования данных из SQL таблицы в другой ресурс по API
   
   
   Примеры находятся в папке examples_tests
   
## instalation

- git clone git@github.com:dionisvl/Replication.git
- composer update
- create DB "repl" in your DBMS 
- open your_domain/examples_tests