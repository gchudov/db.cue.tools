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
CREATE TYPE style_t AS ENUM (E'Deep House',E'Techno',E'Tech House',E'House',E'Abstract',E'Ambient',E'Breaks',E'Tribal House',E'Trance',E'Dub',E'Breakbeat',E'Dark Ambient',E'Garage House',E'Leftfield',E'Illbient',E'IDM',E'Broken Beat',E'Dub Techno',E'Minimal',E'Drum n Bass',E'Experimental',E'Acid',E'Acid House',E'Tribal',E'Latin',E'Jazzdance',E'Breakcore',E'Electro',E'Downtempo',E'Future Jazz',E'Trip Hop',E'Jungle',E'Field Recording',E'Noise',E'Industrial',E'Goa Trance',E'Cut-up/DJ',E'Comedy',E'Dialogue',E'Big Beat',E'Hardcore',E'Lounge',E'Disco',E'Jazz-Funk',E'Afro-Cuban Jazz',E'Contemporary Jazz',E'Vocal',E'UK Garage',E'Psychedelic Rock',E'Hard Trance',E'Salsa',E'Acid Jazz',E'Progressive Trance',E'Progressive House',E'Glitch',E'Neo Soul',E'Hard House',E'Funk',E'Modern Classical',E'Hip Hop',E'Interview',E'Fusion',E'Bossanova',E'Soul',E'Instrumental',E'Soul-Jazz',E'Afrobeat',E'Ghetto',E'Hip-House',E'Synth-pop',E'Easy Listening',E'Euro House',E'Smooth Jazz',E'New Wave',E'EBM',E'Europop',E'Post Rock',E'Speed Garage',E'New Beat',E'Freestyle',E'Speedcore',E'Reggae',E'Roots Reggae',E'Alternative Rock',E'Indie Rock',E'Pop Rock',E'Glam',E'Bossa Nova',E'Latin Jazz',E'Avantgarde',E'J-pop',E'Free Jazz',E'MPB',E'Samba',E'Psy-Trance',E'Ethereal',E'Brit Pop',E'Soundtrack',E'Mambo',E'Ska',E'Conscious',E'Italodance',E'Happy Hardcore',E'Gabber',E'Rhythmic Noise',E'Acoustic',E'Space Rock',E'Lo-Fi',E'Drone',E'Score',E'Shoegaze',E'Shoegazer',E'Chiptune',E'Musique Concrète',E'Free Funk',E'Neofolk',E'Merengue',E'Cha-Cha',E'Cumbia',E'Rumba',E'Big Band',E'Baroque',E'Reggae-Pop',E'DJ Battle Tool',E'Spoken Word',E'Folk Rock',E'Hi NRG',E'Italo-Disco',E'Pop Rap',E'Krautrock',E'Garage Rock',E'Punk',E'Art Rock',E'Goth Rock',E'Math Rock',E'Jumpstyle',E'New Age',E'Celtic',E'Space-Age',E'Rock & Roll',E'Hard Bop',E'Ragga HipHop',E'Bass Music',E'Dancehall',E'Contemporary',E'Hindustani',E'Soft Rock',E'Hard Rock',E'Black Metal',E'Heavy Metal',E'Ballad',E'Bounce',E'Free Improvisation',E'Rhythm & Blues',E'Electric Blues',E'Grindcore',E'Gangsta',E'Hardstyle',E'Aboriginal',E'Folk',E'Prog Rock',E'Country Rock',E'Soca',E'RnB/Swing',E'Darkwave',E'Gospel',E'Tango',E'Political',E'Nueva Trova',E'Descarga',E'Cubano',E'Death Metal',E'Thug Rap',E'Psychedelic',E'Nu Metal',E'P.Funk',E'Afro-Cuban',E'Boogaloo',E'Jazz-Rock',E'Brass Band',E'Britcore',E'Therapy',E'Speech',E'Movie Effects',E'Sermon',E'Swing',E'Power Electronics',E'Cool Jazz',E'Funk Metal',E'Grime',E'Ragga',E'Theme',E'Flamenco',E'Go-Go',E'Piano Blues',E'Classic Rock',E'Blues Rock',E'Dubstep',E'Novelty',E'Nitzhonot',E'Swingbeat',E'Thrash',E'Classical',E'Neo-Classical',E'Jazzy Hip-Hop',E'New Jack Swing',E'Psychobilly',E'Makina',E'Indian Classical',E'Schlager',E'Gogo',E'Surf',E'Post Bop',E'Steel Band',E'Modal',E'Bayou Funk',E'Doom Metal',E'Chanson',E'Modern',E'Post-Modern',E'Bop',E'Batucada',E'No Wave',E'African',E'Calypso',E'Horrorcore',E'Monolog',E'Sonero',E'Country Blues',E'Grunge',E'Mod',E'Poetry',E'Promotional',E'Country',E'Rockabilly',E'Power Pop',E'Southern Rock',E'Post-Punk',E'Hardcore Hip-Hop',E'Ragtime',E'Medieval',E'Gamelan',E'Gypsy Jazz',E'Special Effects',E'Stoner Rock',E'Modern Electric Blues',E'Parody',E'Radioplay',E'Lovers Rock',E'Rocksteady',E'Military',E'Ranchera',E'Romantic',E'Dub Poetry',E'Avant-garde Jazz',E'Guaguancó',E'Bolero',E'Screw',E'Bluegrass',E'Music Hall',E'Bhangra',E'Audiobook',E'Symphonic Rock',E'Honky Tonk',E'Bollywood',E'Oi',E'Religious',E'Harmonica Blues',E'Opera',E'Arena Rock',E'Speed Metal',E'Marches',E'Crunk',E'Pacific',E'R&B/Swing',E'Trip-Hop',E'Marimba',E'Emo',E'Doo Wop',E'Overtone Singing',E'Ottoman Classical',E'Rebetiko',E'Raï',E'Bachata',E'Reggaeton',E'Nordic',E'Education',E'Story',E'Tejano',E'Kwaito',E'Technical',E'Highlife',E'Viking Metal',E'Neo-Romantic',E'Beat',E'Chicago Blues',E'Reggae Gospel',E'Delta Blues',E'Dixieland',E'Musical',E'Mariachi',E'Skiffle',E'Son',E'Trova',E'Zouk',E'Polka',E'Acid Rock',E'Danzon',E'Louisiana Blues',E'Pachanga',E'Lambada',E'Romani',E'Educational',E'Sephardic',E'Klezmer',E'Renaissance',E'Conjunto',E'Early',E'Cajun',E'East Coast Blues',E'Enka',E'Kaseko',E'Fado',E'Forro',E'Mouth Music',E'Jibaro',E'Charanga',E'Jump Blues',E'Karaoke',E'Public Service Announcement',E'Mento',E'Compas',E'Piedmont Blues',E'Texas Blues',E'Dialog',E'Nueva Cancion',E'Rapso',E'Pipe & Drum',E'Zydeco',E'Beguine',E'Nursery Rhymes',E'Hyphy',E'Quechua',E'Twelve-tone',E'Vallenato',E'Canzone Napoletana',E'Junkanoo',E'Norteño',E'Timba',E'Bongo Flava',E'Hiplife',E'Impressionist',E'Éntekhno',E'Laïkó',E'Soukous',E'Corrido',E'Rune Singing',E'Carnatic',E'Mizrahi',E'Spaza',E'Serial',E'Plena',E'Persian Classical',E'Motswako',E'Favela Funk',E'Cuatro',E'Korean Court Music',E'Griot',E'Mugham',E'Chinese Classical',E'Gagaku',E'Cape Jazz',E'Klasik',E'Luk Thung',E'Andalusian Classical',E'Lao Music',E'Unknown',E'Bangladeshi Classical',E'Thai Classical',E'Sámi Music',E'Philippine Classical',E'Cambodian Classical',E'Piobaireachd');
CREATE TYPE format_t AS ENUM ('Vinyl','CD','Cassette','Box Set','All Media','CDr','File','Floppy Disk','Flexi-disc','DAT','Minidisc','Lathe Cut','DVD','CDV','Hybrid','VHS','Acetate','DVDr','Shellac','8-Track Cartridge','MVD','Laserdisc','Reel-To-Reel','Memory Stick','Betamax','DCC','UMD','Microcassette','HD DVD','Blu-ray','Cylinder','DualDisc','4-Track Cartridge','Book','Edison Disc','Datassette','SelectaVision','Blu-ray-R','Video 2000');
CREATE TYPE description_t AS ENUM (E'12"',E'Compilation',E'Mixed',E'Album',E'Limited Edition',E'EP',E'Repress',E'33 ⅓ RPM',E'45 RPM',E'LP',E'10"',E'Promo',E'Maxi-Single',E'Mini-Album',E'Single',E'Enhanced',E'7"',E'Reissue',E'Single Sided',E'Sampler',E'Partially Mixed',E'Remastered',E'White Label',E'Unofficial Release',E'Test Pressing',E'Picture Disc',E'Etched',E'Mini',E'Stereo',E'MP3',E'CD-ROM',E'Mispress',E'3.5"',E'Shape',E'VCD',E'5"',E'Mono',E'Minimax',E'Misprint',E'8"',E'HDCD',E'Partially Unofficial',E'11"',E'CDi',E'Business Card',E'Copy Protected',E'DVD-Video',E'SVCD',E'PAL',E'DVDplus',E'Miscellaneous',E'NTSC',E'ogg-vorbis',E'Quadraphonic',E'12',E'78 RPM',E'9"',E'16 ⅔ RPM',E'Double Sided',E'CD+G',E'DVD-Audio',E'SACD',E'Dolby 5.1',E'MPEG-4',E'Multichannel',E'DualDisc',E'AAC',E'SECAM',E'WAV',E'DVD-Data',E'6"',E'WMA',E'DVD Audio',E'3 ¾ ips',E'AVCD',E'12&quot;',E'Card Backed',E'FLAC',E'DVD-9',E'MP3 Surround',E'4"',E'7 ½ ips',E'½"',E'10&quot;',E'7',E'12\\',E'DVD-10',E'¼"',E'MPEG-4 Video',E'DVD-18',E'Ambisonic',E'WMV',E'7&quot;',E'10',E'5.25"',E'AIFF',E'8&quot;',E'16"',E'VinylDisc',E'ALAC',E'FLV',E'33 â…“ RPM',E'4 Minute',E'2 Minute',E'33 â�� RPM',E'SWF',E'30 ips',E'Concert',E'AIFC');

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

