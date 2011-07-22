--
-- PostgreSQL database dump
--

SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: artist; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TYPE release_status_t AS ENUM ('Accepted', 'Hmm');
CREATE TYPE image_type_t AS ENUM ('primary', 'secondary');
CREATE TYPE genre_t AS ENUM ('Electronic','Hip Hop','Non-Music','Jazz','Rock','Latin','Funk / Soul','Reggae','Pop','Folk, World, & Country','Stage & Screen','Classical','Blues','Brass & Military','Children''s');
CREATE TYPE format_t AS ENUM ('Vinyl','CD','Cassette','Box Set','All Media','CDr','File','Floppy Disk','Flexi-disc','DAT','Minidisc','Lathe Cut','DVD','CDV','Hybrid','VHS','Acetate','DVDr','Shellac','8-Track Cartridge','MVD','Laserdisc','Reel-To-Reel','Memory Stick','Betamax','DCC','UMD','Microcassette','HD DVD','Blu-ray','Cylinder','DualDisc','4-Track Cartridge','Book','Edison Disc','Datassette','SelectaVision','Blu-ray-R','Video 2000');

-- CREATE TABLE artist (
--     name text NOT NULL,
--     realname text,
--     urls text[],
--     namevariations text[],
--     aliases text[],
--     releases integer[],
--     profile text,
--     members text[],
--     groups text[]
-- );


--
-- Name: artists_images; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

-- CREATE TABLE artists_images (
--     image_uri text,
--     artist_name text
-- );


--
-- Name: country; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

-- CREATE TABLE country (
--     name text
-- );


--
-- Name: format; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

-- CREATE TABLE format (
--     name text NOT NULL
-- );


--
-- Name: genre; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

-- CREATE TABLE genre (
--     id integer NOT NULL,
--     name text,
--     parent_genre integer,
--     sub_genre integer
-- );

--
-- Name: image; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE image (
    id integer NOT NULL,
    image_type image_type_t NOT NULL,
    uri text NOT NULL,
    height integer,
    width integer,
    uri150 text
);


--
-- Name: label; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE label (
    id integer NOT NULL,
    name text NOT NULL
--     contactinfo text,
--     profile text,
--     parent_label text,
--     sublabels text[],
--     urls text[]
);


--
-- Name: labels_images; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

-- CREATE TABLE labels_images (
--     image_uri integer NOT NULL,
--     label_name text
-- );


--
-- Name: release; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE release (
    discogs_id integer NOT NULL,
    master_id integer,
    status release_status_t,
    artist_credit integer,
    extra_artists integer,
    title text,
    country text,
    released text,
    notes text,
    genres genre_t[],
    styles style_t[]
);

CREATE TABLE artist_credit (
    id integer NOT NULL,
    name integer NOT NULL,
    count integer NOT NULL
);

CREATE TABLE artist_credit_name (
    artist_credit integer NOT NULL,
    position integer NOT NULL,
    name integer,
    anv integer,
    join_verb text,
    role text,
    tracks text
);

CREATE TABLE artist_name (
    id integer NOT NULL,
    name text
);

--
-- Name: releases_formats; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE releases_formats (
    release_id integer NOT NULL,
    format_name format_t NOT NULL,
    qty integer,
    descriptions description_t[]
);

--
-- Name: releases_images; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE releases_images (
    release_id integer NOT NULL,
    image_id integer NOT NULL
);


--
-- Name: releases_labels; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE releases_labels (
    label integer,
    release_id integer NOT NULL,
    catno text
);


--
-- Name: role; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

--  CREATE TABLE role (
--     role_name text
-- );


--
-- Name: track; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE track (
    release_id integer NOT NULL,
    artist_credit integer,
    extra_artists integer,
    title text,
    duration integer,
    "position" text,
    trno integer,
    discno integer
);

--
-- Name: artist_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

-- ALTER TABLE ONLY artist
--     ADD CONSTRAINT artist_pkey PRIMARY KEY (name);


--
-- Name: format_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

-- ALTER TABLE ONLY format
--     ADD CONSTRAINT format_pkey PRIMARY KEY (name);


--
-- Name: genre_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

-- ALTER TABLE ONLY genre
--     ADD CONSTRAINT genre_pkey PRIMARY KEY (id);


--
-- Name: image_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY image
    ADD CONSTRAINT image_pkey PRIMARY KEY (id);

--
-- Name: label_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY label
    ADD CONSTRAINT label_pkey PRIMARY KEY (id);


--
-- Name: release_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY release
    ADD CONSTRAINT release_pkey PRIMARY KEY (discogs_id);

ALTER TABLE ONLY artist_credit
    ADD CONSTRAINT artist_credit_pkey PRIMARY KEY (id);
--
-- Name: artists_images_artist_name_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

-- ALTER TABLE ONLY artists_images
--     ADD CONSTRAINT artists_images_artist_name_fkey FOREIGN KEY (artist_name) REFERENCES artist(name);


--
-- Name: artists_images_image_uri_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

-- ALTER TABLE ONLY artists_images
--     ADD CONSTRAINT artists_images_image_uri_fkey FOREIGN KEY (image_uri) REFERENCES image(uri);


--
-- Name: foreign_did; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_labels
    ADD CONSTRAINT releases_labels_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);

ALTER TABLE ONLY releases_labels
    ADD CONSTRAINT releases_labels_label_id_fkey FOREIGN KEY (label_id) REFERENCES label(id);


--
-- Name: labels_images_image_uri_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

-- ALTER TABLE ONLY labels_images
--     ADD CONSTRAINT labels_images_image_uri_fkey FOREIGN KEY (image_uri) REFERENCES image(uri);


--
-- Name: labels_images_label_name_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

-- ALTER TABLE ONLY labels_images
--     ADD CONSTRAINT labels_images_label_name_fkey FOREIGN KEY (label_name) REFERENCES label(name);


--
-- Name: releases_formats_discogs_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_formats
    ADD CONSTRAINT releases_formats_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);


--
-- Name: releases_formats_format_name_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

-- ALTER TABLE ONLY releases_formats
--     ADD CONSTRAINT releases_formats_format_name_fkey FOREIGN KEY (format_name) REFERENCES format(name);


--
-- Name: releases_images_discogs_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

CREATE INDEX releases_images_release_id_index ON releases_images(release_id);

ALTER TABLE ONLY release
    ADD CONSTRAINT release_artist_credit_fkey FOREIGN KEY (artist_credit) REFERENCES artist_credit(id);

ALTER TABLE ONLY track
    ADD CONSTRAINT track_artist_credit_fkey FOREIGN KEY (artist_credit) REFERENCES artist_credit(id);

ALTER TABLE ONLY track
    ADD CONSTRAINT track_extra_artists_fkey FOREIGN KEY (extra_artists) REFERENCES artist_credit(id);

ALTER TABLE ONLY releases_images
    ADD CONSTRAINT releases_images_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);

--
-- Name: releases_images_image_uri_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_images
    ADD CONSTRAINT releases_images_image_id_fkey FOREIGN KEY (image_id) REFERENCES image(id);


ALTER TABLE ONLY track
    ADD CONSTRAINT track_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);
--
-- PostgreSQL database dump complete
--

