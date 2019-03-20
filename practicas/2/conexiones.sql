-- La base de datos utilizada es PostgreSQL
-- Te preguntaras, ¿Por que PostgreSQL? ¿Tambien esta MySQL no?
-- Y es correcto, pero yo solo uso calidad

create table conexiones(
    email varchar(500),
    fecha timestamp default current_timestamp
);
