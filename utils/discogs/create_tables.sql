--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

--
-- Name: image_type_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE image_type_t AS ENUM (
    'primary',
    'secondary'
);


--
-- Name: release_status_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE release_status_t AS ENUM (
    'Accepted',
    'Deleted',
    'Draft'
);

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: artist_credit; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE artist_credit (
    id integer NOT NULL,
    name integer NOT NULL,
    count integer NOT NULL
);


--
-- Name: artist_credit_name; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE artist_credit_name (
    artist_credit integer NOT NULL,
    "position" integer NOT NULL,
    name integer,
    anv integer,
    join_verb text,
    role text,
    tracks text
);


--
-- Name: artist_name; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE artist_name (
    id integer NOT NULL,
    name text
);


CREATE TABLE track_title (
    id integer NOT NULL,
    name text NOT NULL
);


--
-- Name: image; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE video (
    id integer NOT NULL,
    src text NOT NULL,
    title text,
    duration integer
);


--
-- Name: label; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE label (
    id integer NOT NULL,
    name text NOT NULL
);


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
    image_type image_type_t NOT NULL,
    uri text NOT NULL,
    height integer,
    width integer
);

CREATE TABLE releases_identifiers (
    release_id integer NOT NULL,
    id_type idtype_t NOT NULL,
    id_value text NOT NULL
);

CREATE TABLE releases_videos (
    release_id integer NOT NULL,
    video_id integer NOT NULL
);

--
-- Name: releases_labels; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE releases_labels (
    label_id integer,
    release_id integer NOT NULL,
    catno text
);

CREATE TABLE toc (
    discogs_id integer NOT NULL,
    disc integer NOT NULL,
    duration integer[]
);

--
-- Name: track; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE track (
    release_id integer NOT NULL,
    artist_credit integer,
    extra_artists integer,
    title integer,
    duration integer,
    "position" text,
    "index" integer,
    trno integer,
    discno integer
);

