-- CTDB Database Schema
-- Extracted: 2026-02-06
-- PostgreSQL 16.8
--
-- To refresh: docker exec postgres16 pg_dump -U postgres -d ctdb --schema-only --no-owner --no-privileges --no-comments

-- Extensions
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;

-- Functions
CREATE FUNCTION public.notify_stats_update() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    PERFORM pg_notify('stats_update', json_build_object(
        'type', 'submission',
        'timestamp', extract(epoch from now())
    )::text);
    RETURN NEW;
END;
$$;

-- Tables

CREATE TABLE public.drives (
    name text NOT NULL
);

CREATE TABLE public.entry_release_group (
    entry integer,
    mbid text
);

CREATE TABLE public.failed_submissions (
    subid integer NOT NULL DEFAULT nextval('public.failed_submissions_subid_seq'::regclass),
    entryid integer,
    userid text,
    agent text,
    "time" timestamp without time zone,
    ip text,
    drivename text,
    barcode text,
    quality integer,
    reason text
);

CREATE TABLE public.hourly_stats (
    hour timestamp without time zone NOT NULL,
    eac integer NOT NULL,
    cueripper integer NOT NULL,
    cuetools integer NOT NULL
);

CREATE TABLE public.legacy_users (
    login text NOT NULL,
    realm text NOT NULL,
    passwd text NOT NULL,
    admin boolean
);

CREATE TABLE public.stats_agents (
    label text NOT NULL,
    cnt integer
);

CREATE TABLE public.stats_drives (
    label text NOT NULL,
    cnt integer
);

CREATE TABLE public.stats_pregaps (
    label text NOT NULL,
    cnt integer
);

CREATE TABLE public.stats_totals (
    kind text,
    val integer,
    maxid integer
);

CREATE TABLE public.submissions (
    subid integer NOT NULL DEFAULT nextval('public.submissions_subid_seq'::regclass),
    entryid integer NOT NULL,
    userid text,
    agent text,
    "time" timestamp without time zone,
    ip text,
    drivename text,
    barcode text,
    quality integer
);

CREATE TABLE public.submissions2 (
    id integer NOT NULL DEFAULT nextval('public.submissions2_id_seq'::regclass),
    tocid text NOT NULL,
    crc32 integer NOT NULL,
    parity text NOT NULL,
    trackcount integer NOT NULL,
    audiotracks integer NOT NULL,
    firstaudio integer NOT NULL,
    trackoffsets text NOT NULL,
    artist text,
    title text,
    s3 boolean DEFAULT false NOT NULL,
    hasparity boolean DEFAULT false NOT NULL,
    subcount integer DEFAULT 1 NOT NULL,
    s3new boolean DEFAULT false NOT NULL,
    syndrome bytea,
    track_crcs integer[]
);

CREATE TABLE public.users (
    email text NOT NULL,
    role text DEFAULT 'user'::text NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    last_login timestamp without time zone
);

-- Primary Keys

ALTER TABLE ONLY public.drives ADD CONSTRAINT drives_pkey PRIMARY KEY (name);
ALTER TABLE ONLY public.hourly_stats ADD CONSTRAINT hourly_stats_pkey PRIMARY KEY (hour);
ALTER TABLE ONLY public.stats_agents ADD CONSTRAINT stats_agents_pkey PRIMARY KEY (label);
ALTER TABLE ONLY public.stats_drives ADD CONSTRAINT stats_drives_pkey PRIMARY KEY (label);
ALTER TABLE ONLY public.stats_pregaps ADD CONSTRAINT stats_pregaps_pkey PRIMARY KEY (label);
ALTER TABLE ONLY public.submissions2 ADD CONSTRAINT submissions2_pkey1 PRIMARY KEY (id);
ALTER TABLE ONLY public.submissions ADD CONSTRAINT submissions_pkey PRIMARY KEY (subid);
ALTER TABLE ONLY public.users ADD CONSTRAINT users_pkey PRIMARY KEY (email);

-- Indexes

CREATE INDEX failed_submissions_pkey1 ON public.failed_submissions USING btree (subid);
CREATE INDEX idx_users_role ON public.users USING btree (role);
CREATE INDEX submissions2_artist_idx ON public.submissions2 USING gin (artist public.gin_trgm_ops);
CREATE INDEX submissions2_hasparity_true_s3_false_idx ON public.submissions2 USING btree (id) WHERE ((hasparity = true) AND (s3 = false));
CREATE INDEX submissions2_s3_index ON public.submissions2 USING btree (s3) WHERE (hasparity AND (NOT s3));
CREATE INDEX submissions2_subcount_index ON public.submissions2 USING btree (subcount, id) WHERE (subcount >= 5);
CREATE INDEX submissions_entryid_index ON public.submissions USING btree (entryid);
CREATE INDEX submissions_time ON public.submissions USING btree ("time");
CREATE INDEX submissions_uid ON public.submissions USING btree (userid);
CREATE INDEX tocid_index ON public.submissions2 USING btree (tocid);

-- Extended Statistics

CREATE STATISTICS public.submissions2_hasparity_s3_deps (dependencies) ON s3, hasparity FROM public.submissions2;

-- Triggers

CREATE TRIGGER stats_update_trigger AFTER INSERT ON public.submissions FOR EACH ROW EXECUTE FUNCTION public.notify_stats_update();

-- Foreign Keys

ALTER TABLE ONLY public.submissions ADD CONSTRAINT submissions_fk_id FOREIGN KEY (entryid) REFERENCES public.submissions2(id) ON DELETE CASCADE;
