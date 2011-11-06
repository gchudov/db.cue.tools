--
-- PostgreSQL database dump
--

--
-- Name: freedb_category_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE freedb_category_t AS ENUM (
    'blues',
    'classical',
    'country',
    'data',
    'folk',
    'jazz',
    'misc',
    'newage',
    'reggae',
    'rock',
    'soundtrack'
);


--
-- Name: artist_names; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE artist_names (
    id integer NOT NULL,
    name text NOT NULL
);


--
-- Name: entries; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE entries (
    id integer NOT NULL,
    freedbid integer NOT NULL,
    category freedb_category_t NOT NULL,
    year integer,
    genre integer,
    artist integer,
    title text,
    extra text,
    offsets integer[] NOT NULL
);


--
-- Name: genre_names; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE genre_names (
    id integer NOT NULL,
    name text NOT NULL
);


--
-- Name: tracks; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tracks (
    id integer NOT NULL,
    number integer NOT NULL,
    artist integer,
    extra text,
    title text
);

CREATE LANGUAGE plpgsql;

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

--
-- PostgreSQL database dump complete
--

