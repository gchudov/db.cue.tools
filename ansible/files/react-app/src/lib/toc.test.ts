import { describe, it, expect } from 'vitest'
import { tocs2mbid, tocs2mbtoc, sectorsToTime, buildTracks } from './toc'

describe('tocs2mbid', () => {
  it('should compute correct mbid for simple 2-track TOC', async () => {
    const toc = '0:142936:241345'
    const expected = 'QPj1mt_1RhAYZpUGYE0jdsW9B1s-'
    const result = await tocs2mbid(toc)
    expect(result).toBe(expected)
  })

  it('should compute correct mbid for multi-track TOC', async () => {
    // Example: 25-track album
    // Note: This is different from the API's "Disc Id" column which uses toc2tocid
    const toc = '0:9938:23447:35429:48520:58801:71563:83098:96971:109853:119549:130738:141826:154370:164183:177133:189050:198045:205804:216453:225891:238806:249623:264744:276494:286565'
    const expected = '6yfJIrA5TUAk7DfZ0zHxCl33NQQ-'
    const result = await tocs2mbid(toc)
    expect(result).toBe(expected)
  })
})

describe('tocs2mbtoc', () => {
  it('should convert TOC to MusicBrainz format', () => {
    const toc = '0:142936:241345'
    const result = tocs2mbtoc(toc)
    // Format: "1 <lastaudio> <leadout+150> <track0+150> <track1+150> ..."
    expect(result).toBe('1 2 241495 150 143086')
  })
})

describe('sectorsToTime', () => {
  it('should convert sectors to MM:SS.FF format', () => {
    expect(sectorsToTime(0)).toBe('00:00.00')
    expect(sectorsToTime(75)).toBe('00:01.00')  // 75 sectors = 1 second
    expect(sectorsToTime(150)).toBe('00:02.00')
    expect(sectorsToTime(4500)).toBe('01:00.00')  // 60 seconds
    expect(sectorsToTime(4537)).toBe('01:00.37')  // 60 seconds + 37 frames
  })
})

describe('buildTracks', () => {
  it('should build track list from TOC', () => {
    const toc = '0:10000:20000'
    const tracks = buildTracks(toc, null, null, null)
    
    expect(tracks).toHaveLength(2)
    expect(tracks[0].number).toBe(1)
    expect(tracks[0].startSector).toBe(150)
    expect(tracks[1].number).toBe(2)
    expect(tracks[1].startSector).toBe(10150)
  })

  it('should include track names from tracklist', () => {
    const toc = '0:10000:20000'
    const tracklist = [
      { name: 'Track One', artist: 'Artist' },
      { name: 'Track Two', artist: 'Artist' },
    ]
    const tracks = buildTracks(toc, null, tracklist, 'Artist')
    
    expect(tracks[0].name).toBe('Track One')
    expect(tracks[1].name).toBe('Track Two')
    expect(tracks[0].artist).toBeNull()  // Same as main artist
  })

  it('should show different artist if not main artist', () => {
    const toc = '0:10000:20000'
    const tracklist = [
      { name: 'Track One', artist: 'Main Artist' },
      { name: 'Track Two', artist: 'Guest Artist' },
    ]
    const tracks = buildTracks(toc, null, tracklist, 'Main Artist')
    
    expect(tracks[0].artist).toBeNull()
    expect(tracks[1].artist).toBe('Guest Artist')
  })
})

