CREATE LANGUAGE plpgsql;
CREATE TABLE entries (id integer not null, category integer not null, length integer, 
    offsets integer array, year integer, artist text, title text, genre text, 
    extra text, track_title text array, track_extra text array);
CREATE INDEX entries_id on entries(id);
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
           SET length = new.length, offsets = new.offsets,
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
