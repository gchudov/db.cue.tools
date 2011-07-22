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
-- Name: description_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE description_t AS ENUM (
    '12"',
    'Compilation',
    'Mixed',
    'Album',
    'Limited Edition',
    'EP',
    'Repress',
    '33 ⅓ RPM',
    '45 RPM',
    'LP',
    '10"',
    'Promo',
    'Maxi-Single',
    'Mini-Album',
    'Single',
    'Enhanced',
    '7"',
    'Reissue',
    'Single Sided',
    'Sampler',
    'Partially Mixed',
    'Remastered',
    'White Label',
    'Unofficial Release',
    'Test Pressing',
    'Picture Disc',
    'Etched',
    'Mini',
    'Stereo',
    'MP3',
    'CD-ROM',
    'Mispress',
    '3.5"',
    'Shape',
    'VCD',
    '5"',
    'Mono',
    'Minimax',
    'Misprint',
    '8"',
    'HDCD',
    'Partially Unofficial',
    '11"',
    'CDi',
    'Business Card',
    'Copy Protected',
    'DVD-Video',
    'SVCD',
    'PAL',
    'DVDplus',
    'Miscellaneous',
    'NTSC',
    'ogg-vorbis',
    'Quadraphonic',
    '12',
    '78 RPM',
    '9"',
    '16 ⅔ RPM',
    'Double Sided',
    'CD+G',
    'DVD-Audio',
    'SACD',
    'Dolby 5.1',
    'MPEG-4',
    'Multichannel',
    'DualDisc',
    'AAC',
    'SECAM',
    'WAV',
    'DVD-Data',
    '6"',
    'WMA',
    'DVD Audio',
    '3 ¾ ips',
    'AVCD',
    '12&quot;',
    'Card Backed',
    'FLAC',
    'DVD-9',
    'MP3 Surround',
    '4"',
    '7 ½ ips',
    '½"',
    '10&quot;',
    '7',
    '12\\',
    'DVD-10',
    '¼"',
    'MPEG-4 Video',
    'DVD-18',
    'Ambisonic',
    'WMV',
    '7&quot;',
    '10',
    '5.25"',
    'AIFF',
    '8&quot;',
    '16"',
    'VinylDisc',
    'ALAC',
    'FLV',
    '33 â…“ RPM',
    '4 Minute',
    '2 Minute',
    '33 â�� RPM',
    'SWF',
    '30 ips',
    'Concert',
    'AIFC'
);


--
-- Name: format_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE format_t AS ENUM (
    'Vinyl',
    'CD',
    'Cassette',
    'Box Set',
    'All Media',
    'CDr',
    'File',
    'Floppy Disk',
    'Flexi-disc',
    'DAT',
    'Minidisc',
    'Lathe Cut',
    'DVD',
    'CDV',
    'Hybrid',
    'VHS',
    'Acetate',
    'DVDr',
    'Shellac',
    '8-Track Cartridge',
    'MVD',
    'Laserdisc',
    'Reel-To-Reel',
    'Memory Stick',
    'Betamax',
    'DCC',
    'UMD',
    'Microcassette',
    'HD DVD',
    'Blu-ray',
    'Cylinder',
    'DualDisc',
    '4-Track Cartridge',
    'Book',
    'Edison Disc',
    'Datassette',
    'SelectaVision',
    'Blu-ray-R',
    'Video 2000'
);


--
-- Name: genre_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE genre_t AS ENUM (
    'Electronic',
    'Hip Hop',
    'Non-Music',
    'Jazz',
    'Rock',
    'Latin',
    'Funk / Soul',
    'Reggae',
    'Pop',
    'Folk, World, & Country',
    'Stage & Screen',
    'Classical',
    'Blues',
    'Brass & Military',
    'Children''s'
);


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
    'Draft'
);


--
-- Name: style_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE style_t AS ENUM (
    'Deep House',
    'Techno',
    'Tech House',
    'House',
    'Abstract',
    'Ambient',
    'Breaks',
    'Tribal House',
    'Trance',
    'Dub',
    'Breakbeat',
    'Dark Ambient',
    'Garage House',
    'Leftfield',
    'Illbient',
    'IDM',
    'Broken Beat',
    'Dub Techno',
    'Minimal',
    'Drum n Bass',
    'Experimental',
    'Acid',
    'Acid House',
    'Tribal',
    'Latin',
    'Jazzdance',
    'Breakcore',
    'Electro',
    'Downtempo',
    'Future Jazz',
    'Trip Hop',
    'Jungle',
    'Field Recording',
    'Noise',
    'Industrial',
    'Goa Trance',
    'Cut-up/DJ',
    'Comedy',
    'Dialogue',
    'Big Beat',
    'Hardcore',
    'Lounge',
    'Disco',
    'Jazz-Funk',
    'Afro-Cuban Jazz',
    'Contemporary Jazz',
    'Vocal',
    'UK Garage',
    'Psychedelic Rock',
    'Hard Trance',
    'Salsa',
    'Acid Jazz',
    'Progressive Trance',
    'Progressive House',
    'Glitch',
    'Neo Soul',
    'Hard House',
    'Funk',
    'Modern Classical',
    'Hip Hop',
    'Interview',
    'Fusion',
    'Bossanova',
    'Soul',
    'Instrumental',
    'Soul-Jazz',
    'Afrobeat',
    'Ghetto',
    'Hip-House',
    'Synth-pop',
    'Easy Listening',
    'Euro House',
    'Smooth Jazz',
    'New Wave',
    'EBM',
    'Europop',
    'Post Rock',
    'Speed Garage',
    'New Beat',
    'Freestyle',
    'Speedcore',
    'Reggae',
    'Roots Reggae',
    'Alternative Rock',
    'Indie Rock',
    'Pop Rock',
    'Glam',
    'Bossa Nova',
    'Latin Jazz',
    'Avantgarde',
    'J-pop',
    'Free Jazz',
    'MPB',
    'Samba',
    'Psy-Trance',
    'Ethereal',
    'Brit Pop',
    'Soundtrack',
    'Mambo',
    'Ska',
    'Conscious',
    'Italodance',
    'Happy Hardcore',
    'Gabber',
    'Rhythmic Noise',
    'Acoustic',
    'Space Rock',
    'Lo-Fi',
    'Drone',
    'Score',
    'Shoegaze',
    'Shoegazer',
    'Chiptune',
    'Musique Concrète',
    'Free Funk',
    'Neofolk',
    'Merengue',
    'Cha-Cha',
    'Cumbia',
    'Rumba',
    'Big Band',
    'Baroque',
    'Reggae-Pop',
    'DJ Battle Tool',
    'Spoken Word',
    'Folk Rock',
    'Hi NRG',
    'Italo-Disco',
    'Pop Rap',
    'Krautrock',
    'Garage Rock',
    'Punk',
    'Art Rock',
    'Goth Rock',
    'Math Rock',
    'Jumpstyle',
    'New Age',
    'Celtic',
    'Space-Age',
    'Rock & Roll',
    'Hard Bop',
    'Ragga HipHop',
    'Bass Music',
    'Dancehall',
    'Contemporary',
    'Hindustani',
    'Soft Rock',
    'Hard Rock',
    'Black Metal',
    'Heavy Metal',
    'Ballad',
    'Bounce',
    'Free Improvisation',
    'Rhythm & Blues',
    'Electric Blues',
    'Grindcore',
    'Gangsta',
    'Hardstyle',
    'Aboriginal',
    'Folk',
    'Prog Rock',
    'Country Rock',
    'Soca',
    'RnB/Swing',
    'Darkwave',
    'Gospel',
    'Tango',
    'Political',
    'Nueva Trova',
    'Descarga',
    'Cubano',
    'Death Metal',
    'Thug Rap',
    'Psychedelic',
    'Nu Metal',
    'P.Funk',
    'Afro-Cuban',
    'Boogaloo',
    'Jazz-Rock',
    'Brass Band',
    'Britcore',
    'Therapy',
    'Speech',
    'Movie Effects',
    'Sermon',
    'Swing',
    'Power Electronics',
    'Cool Jazz',
    'Funk Metal',
    'Grime',
    'Ragga',
    'Theme',
    'Flamenco',
    'Go-Go',
    'Piano Blues',
    'Classic Rock',
    'Blues Rock',
    'Dubstep',
    'Novelty',
    'Nitzhonot',
    'Swingbeat',
    'Thrash',
    'Classical',
    'Neo-Classical',
    'Jazzy Hip-Hop',
    'New Jack Swing',
    'Psychobilly',
    'Makina',
    'Indian Classical',
    'Schlager',
    'Gogo',
    'Surf',
    'Post Bop',
    'Steel Band',
    'Modal',
    'Bayou Funk',
    'Doom Metal',
    'Chanson',
    'Modern',
    'Post-Modern',
    'Bop',
    'Batucada',
    'No Wave',
    'African',
    'Calypso',
    'Horrorcore',
    'Monolog',
    'Sonero',
    'Country Blues',
    'Grunge',
    'Mod',
    'Poetry',
    'Promotional',
    'Country',
    'Rockabilly',
    'Power Pop',
    'Southern Rock',
    'Post-Punk',
    'Hardcore Hip-Hop',
    'Ragtime',
    'Medieval',
    'Gamelan',
    'Gypsy Jazz',
    'Special Effects',
    'Stoner Rock',
    'Modern Electric Blues',
    'Parody',
    'Radioplay',
    'Lovers Rock',
    'Rocksteady',
    'Military',
    'Ranchera',
    'Romantic',
    'Dub Poetry',
    'Avant-garde Jazz',
    'Guaguancó',
    'Bolero',
    'Screw',
    'Bluegrass',
    'Music Hall',
    'Bhangra',
    'Audiobook',
    'Symphonic Rock',
    'Honky Tonk',
    'Bollywood',
    'Oi',
    'Religious',
    'Harmonica Blues',
    'Opera',
    'Arena Rock',
    'Speed Metal',
    'Marches',
    'Crunk',
    'Pacific',
    'R&B/Swing',
    'Trip-Hop',
    'Marimba',
    'Emo',
    'Doo Wop',
    'Overtone Singing',
    'Ottoman Classical',
    'Rebetiko',
    'Raï',
    'Bachata',
    'Reggaeton',
    'Nordic',
    'Education',
    'Story',
    'Tejano',
    'Kwaito',
    'Technical',
    'Highlife',
    'Viking Metal',
    'Neo-Romantic',
    'Beat',
    'Chicago Blues',
    'Reggae Gospel',
    'Delta Blues',
    'Dixieland',
    'Musical',
    'Mariachi',
    'Skiffle',
    'Son',
    'Trova',
    'Zouk',
    'Polka',
    'Acid Rock',
    'Danzon',
    'Louisiana Blues',
    'Pachanga',
    'Lambada',
    'Romani',
    'Educational',
    'Sephardic',
    'Klezmer',
    'Renaissance',
    'Conjunto',
    'Early',
    'Cajun',
    'East Coast Blues',
    'Enka',
    'Kaseko',
    'Fado',
    'Forro',
    'Mouth Music',
    'Jibaro',
    'Charanga',
    'Jump Blues',
    'Karaoke',
    'Public Service Announcement',
    'Mento',
    'Compas',
    'Piedmont Blues',
    'Texas Blues',
    'Dialog',
    'Nueva Cancion',
    'Rapso',
    'Pipe & Drum',
    'Zydeco',
    'Beguine',
    'Nursery Rhymes',
    'Hyphy',
    'Quechua',
    'Twelve-tone',
    'Vallenato',
    'Canzone Napoletana',
    'Junkanoo',
    'Norteño',
    'Timba',
    'Bongo Flava',
    'Hiplife',
    'Impressionist',
    'Éntekhno',
    'Laïkó',
    'Soukous',
    'Corrido',
    'Rune Singing',
    'Carnatic',
    'Mizrahi',
    'Spaza',
    'Serial',
    'Plena',
    'Persian Classical',
    'Motswako',
    'Favela Funk',
    'Cuatro',
    'Korean Court Music',
    'Griot',
    'Mugham',
    'Chinese Classical',
    'Gagaku',
    'Cape Jazz',
    'Klasik',
    'Luk Thung',
    'Andalusian Classical',
    'Lao Music',
    'Unknown',
    'Bangladeshi Classical',
    'Thai Classical',
    'Sámi Music',
    'Philippine Classical',
    'Cambodian Classical',
    'Piobaireachd'
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
    image_id integer NOT NULL
);


--
-- Name: releases_labels; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE releases_labels (
    label_id integer,
    release_id integer NOT NULL,
    catno text
);


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

