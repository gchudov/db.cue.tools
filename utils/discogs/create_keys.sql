
--
-- Name: artist_credit_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY artist_credit
    ADD CONSTRAINT artist_credit_pkey PRIMARY KEY (id);


--
-- Name: artist_name_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY artist_name
    ADD CONSTRAINT artist_name_pkey PRIMARY KEY (id);


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

ALTER TABLE ONLY track_title
    ADD CONSTRAINT track_title_pkey PRIMARY KEY (id);


--
-- Name: releases_images_release_id_index; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX releases_images_release_id_index ON releases_images USING btree (release_id);

CREATE INDEX track_release_id_index ON track(release_id);

CREATE INDEX releases_labels_release_id_index ON releases_labels(release_id);

CREATE INDEX release_master_id_index ON release(master_id);

--
-- Name: artist_credit_name_anv_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY artist_credit_name
    ADD CONSTRAINT artist_credit_name_anv_fkey FOREIGN KEY (anv) REFERENCES artist_name(id);


--
-- Name: artist_credit_name_artist_credit_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY artist_credit_name
    ADD CONSTRAINT artist_credit_name_artist_credit_fkey FOREIGN KEY (artist_credit) REFERENCES artist_credit(id);


--
-- Name: artist_credit_name_name_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY artist_credit_name
    ADD CONSTRAINT artist_credit_name_name_fkey FOREIGN KEY (name) REFERENCES artist_name(id);


--
-- Name: release_artist_credit_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY release
    ADD CONSTRAINT release_artist_credit_fkey FOREIGN KEY (artist_credit) REFERENCES artist_credit(id);


--
-- Name: releases_formats_release_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_formats
    ADD CONSTRAINT releases_formats_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);


--
-- Name: releases_images_image_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_images
    ADD CONSTRAINT releases_images_image_id_fkey FOREIGN KEY (image_id) REFERENCES image(id);


--
-- Name: releases_images_release_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_images
    ADD CONSTRAINT releases_images_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);


--
-- Name: releases_labels_label_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_labels
    ADD CONSTRAINT releases_labels_label_id_fkey FOREIGN KEY (label_id) REFERENCES label(id);


--
-- Name: releases_labels_release_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY releases_labels
    ADD CONSTRAINT releases_labels_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);


--
-- Name: track_artist_credit_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY track
    ADD CONSTRAINT track_artist_credit_fkey FOREIGN KEY (artist_credit) REFERENCES artist_credit(id);


--
-- Name: track_extra_artists_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY track
    ADD CONSTRAINT track_extra_artists_fkey FOREIGN KEY (extra_artists) REFERENCES artist_credit(id);


--
-- Name: track_release_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY track
    ADD CONSTRAINT track_release_id_fkey FOREIGN KEY (release_id) REFERENCES release(discogs_id);

ALTER TABLE ONLY track
    ADD CONSTRAINT track_title_fkey FOREIGN KEY (title) REFERENCES track_title(id);

--
-- PostgreSQL database dump complete
--

