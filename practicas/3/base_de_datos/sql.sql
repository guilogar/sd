-- base\_de\_datos/sql.sql
-- La base de datos es PostgreSQL
-- Para instalar en linux: sudo apt install postgresql
-- Para instalar en windows: ni idea
-- Para instalar en mac os: https://www.postgresql.org/download/macosx/

drop table if exists repositorios;
create table repositorios(
    id bigserial primary key,
    full_name varchar(5000),
    branch_name varchar(5000),
    last_commit varchar(1000)
);
