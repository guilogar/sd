
drop table if exists repositorios;
create table repositorios(
    id bigserial primary key,
    full_name varchar(5000),
    branch_name varchar(5000),
    last_commit varchar(1000)
);
