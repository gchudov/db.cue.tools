CREATE LANGUAGE plpgsql;
CREATE TYPE freedb_category_t AS ENUM ('blues','classical','country','data','folk','jazz','misc','newage','reggae','rock','soundtrack');
CREATE TABLE entries (id integer not null, category freedb_category_t not null,
    offsets integer array not null, year integer, artist text, title text, genre text, 
    extra text, track_title text array, track_extra text array);
CREATE UNIQUE INDEX entries_id_category on entries(id, category);
CREATE OR REPLACE FUNCTION entries_insert_before_F()
RETURNS TRIGGER
 AS $BODY$
DECLARE
    result INTEGER; 
BEGIN
    SET SEARCH_PATH TO PUBLIC;
    
    -- Find out if there is a row
    result = (select count(*) from entries
                where id = new.id
                  and category = new.category
               );

    -- On the update branch, perform the update
    -- and then return NULL to prevent the 
    -- original insert from occurring
    IF result = 1 THEN
        UPDATE entries 
           SET offsets = new.offsets,
               year = new.year, artist = new.artist,
               title = new.title, genre = new.genre,
               extra = new.extra, track_title = new.track_title,
               track_extra = new.track_extra
         WHERE id = new.id
           AND category = new.category;
           
        RETURN null;
    END IF;
    
    -- The default branch is to return "NEW" which
    -- causes the original INSERT to go forward
    RETURN new;

END; $BODY$
LANGUAGE 'plpgsql' SECURITY DEFINER;

-- That extremely annoying second command you always
-- need for Postgres triggers.
CREATE TRIGGER entries_insert_before_T
   before insert
   ON entries
   FOR EACH ROW
   EXECUTE PROCEDURE entries_insert_before_F();

CREATE OR REPLACE FUNCTION create_cube_from_toc(offsets INTEGER[]) RETURNS cube AS $$
DECLARE
    point    cube;
    str      VARCHAR;
    i        INTEGER;
    count    INTEGER;
    dest     INTEGER;
    dim      CONSTANT INTEGER = 6;
    selected INTEGER[];
BEGIN
    count = array_upper(offsets, 1) - 1;
    FOR i IN 0..dim LOOP
        selected[i] = 0;
    END LOOP;

    IF count < dim THEN
        FOR i IN 1..count LOOP
            selected[i] = offsets[i + 1] - offsets[i];
        END LOOP;
    ELSE
        FOR i IN 1..count LOOP
            dest = (dim * (i-1) / count) + 1;
            selected[dest] = selected[dest] + offsets[i + 1] - offsets[i];
        END LOOP;
    END IF;

    str = '(';
    FOR i IN 1..dim LOOP
        IF i > 1 THEN
            str = str || ',';
        END IF;
        str = str || cast(selected[i] as text);
    END LOOP;
    str = str || ')';

    RETURN str::cube;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;

CREATE OR REPLACE FUNCTION create_bounding_cube(durations INTEGER[], fuzzy INTEGER) RETURNS cube AS $$
DECLARE
    point    cube;
    str      VARCHAR;
    i        INTEGER;
    dest     INTEGER;
    count    INTEGER;
    dim      CONSTANT INTEGER = 6;
    selected INTEGER[];
    scalers  INTEGER[];
BEGIN

    count = array_upper(durations, 1) - 1;
    IF count < dim THEN
        FOR i IN 1..dim LOOP
            selected[i] = 0;
            scalers[i] = 0;
        END LOOP;
        FOR i IN 1..count LOOP
            selected[i] = durations[i + 1] - durations[i];
            scalers[i] = 1;
        END LOOP;
    ELSE
        FOR i IN 1..dim LOOP
            selected[i] = 0;
            scalers[i] = 0;
        END LOOP;
        FOR i IN 1..count LOOP
            dest = (dim * (i-1) / count) + 1;
            selected[dest] = selected[dest] + durations[i + 1] - durations[i];
            scalers[dest] = scalers[dest] + 1;
        END LOOP;
    END IF;

    str = '(';
    FOR i IN 1..dim LOOP
        IF i > 1 THEN
            str = str || ',';
        END IF;
        str = str || cast((selected[i] - (fuzzy * scalers[i])) as text);
    END LOOP;
    str = str || '),(';
    FOR i IN 1..dim LOOP
        IF i > 1 THEN
            str = str || ',';
        END IF;
        str = str || cast((selected[i] + (fuzzy * scalers[i])) as text);
    END LOOP;
    str = str || ')';

    RETURN str::cube;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;

CREATE INDEX entries_offsets on entries(offsets);
CREATE INDEX entries_offsets_gist_index ON entries USING GIST (create_cube_from_toc(offsets));
