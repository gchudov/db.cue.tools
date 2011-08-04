--
-- Name: artist_names_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY artist_names
    ADD CONSTRAINT artist_names_pkey PRIMARY KEY (id);


--
-- Name: genre_names_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY genre_names
    ADD CONSTRAINT genre_names_pkey PRIMARY KEY (id);


--
-- Name: tracks_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

-- ALTER TABLE ONLY tracks
--    ADD CONSTRAINT tracks_pkey PRIMARY KEY (id, number);

CREATE INDEX tracks_id ON tracks(id);

CREATE UNIQUE INDEX entries_freedbid_category ON entries(freedbid, category);

CREATE INDEX entries_offsets_hash_index ON entries USING HASH (array_to_string(offsets,','));

CREATE INDEX entries_offsets_gist_index ON entries USING GIST (create_cube_from_toc(offsets));
