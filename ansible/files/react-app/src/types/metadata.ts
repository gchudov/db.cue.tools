// Type definitions for Go backend metadata API response
// Matches models in utils/docker/ctdbweb-go/internal/models/metadata.go

export interface Metadata {
  source: string
  id: string
  artistname: string
  albumname: string
  first_release_date_year?: number
  genre?: string
  extra?: string
  tracklist?: Track[]
  label?: Label[]
  discnumber?: number
  totaldiscs?: number
  discname?: string
  barcode?: string
  coverart?: CoverArt[]
  videos?: Video[]
  info_url?: string
  release?: Release[]
  relevance?: number
  discogs_id?: string
}

export interface Track {
  name: string
  artist?: string
  extra?: string
}

export interface Label {
  name: string
  catno?: string
}

export interface CoverArt {
  uri: string
  uri150?: string
  width?: number
  height?: number
  primary: boolean
}

export interface Video {
  uri: string
  title: string
}

export interface Release {
  country?: string
  date?: string
}
