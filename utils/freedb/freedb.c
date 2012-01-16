#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <regex.h> /* Provides regular expression matching */
#include <bzlib.h>
#include <search.h> 

// TODO: keep track of revisions

long disc_length = 0;
int offsets[100];
int n_offsets = 0;
char *dtitle = 0;
char *dyear = 0;
char *dgenre = 0;
char *dext = 0;
char* ttitle[100];
char* t_ext[100];
int c_title = 0;
int c_ext = 0;
int freedbid = 0;
int category = -1;
BZFILE * entries;
//BZFILE * disc_extra;
BZFILE * tracks;
BZFILE * genre_names;
BZFILE * artist_names;
//BZFILE * track_names;
//BZFILE * track_extra;
int bzerror;
struct hsearch_data h_genre_names;
struct hsearch_data h_artist_names;
//struct hsearch_data h_track_names;
//struct hsearch_data h_track_extra;

char * validcategories[] = {"blues","classical","country",
	"data","folk","jazz","misc",
	"newage","reggae","rock","soundtrack"};

void BZ2_bzPrintf(BZFILE *b, const char *fmt, ...)
{
   /* Guess we need no more than 100 bytes. */
   static char *p = 0;
   static int size = 1024;
   int n;
   char *np;
   va_list ap;

   if (!p) p = malloc (size);

   while (1) {
      /* Try to print in the allocated space. */
      va_start(ap, fmt);
      n = vsnprintf (p, size, fmt, ap);
      va_end(ap);
      /* If that worked, return the string. */
      if (n > -1 && n < size) {
         BZ2_bzWrite(&bzerror, b, (void*)p, n);
         return;
      }
      /* Else try again with more space. */
      if (n > -1)    /* glibc 2.1 */
         size = n+1; /* precisely what is needed */
      else           /* glibc 2.0 */
         size *= 2;  /* twice the old size */
      if ((np = realloc (p, size)) == NULL) {
         free(p);
         exit(2);
      } else {
         p = np;
      }
   }    
}

void *xrealloc(void *ptr, size_t size)
{
	void *p = realloc(ptr, size);
	if (!p && size) {
		fprintf(stderr, "Realloc failed.");
		exit(2);
	}
	return p;
}

/*
 *  * If there is a valid UTF-8 char starting at *ps,
 *   * return its value and increment *ps. Otherwise
 *    * return -1 and leave *ps unchanged. All 2^31
 *     * possible UCS chars are accepted.
 *      */
int parse_utf8(const char **ps)
{
	unsigned char *s = (unsigned char *)*ps;
	int wc, n, i;

	if (*s < 0x80) {
		*ps = (char *)s + 1;
		return *s;
	}

	if (*s < 0xc2)
		return -1;

	else if (*s < 0xe0) {
		if ((s[1] & 0xc0) != 0x80)
			return -1;
		*ps = (char *)s + 2;
		return ((s[0] & 0x1f) << 6) | (s[1] & 0x3f);
	}

	else if (*s < 0xf0)
		n = 3;

	else if (*s < 0xf8)
		n = 4;

	else if (*s < 0xfc)
		n = 5;

	else if (*s < 0xfe)
		n = 6;

	else
		return -1;

	wc = *s++ & ((1 << (7 - n)) - 1);
	for (i = 1; i < n; i++) {
		if ((*s & 0xc0) != 0x80)
			return -1;
		wc = (wc << 6) | (*s++ & 0x3f);
	}
	if (wc < (1 << (5 * n - 4)))
		return -1;
	*ps = (char *)s;
	return wc;
}

/*
 *  * Here we define the acceptable characters in a DB entry:
 *   * - ASCII control chars: 9 (tab), 10 (lf), 13 (cr)
 *    * - all printable ASCII: 32..127
 *     * - everything from U+00A0 upwards except:
 *      *   - surrogates: U+D800..U+DFFF
 *       *   - BOM and reverse BOM: U+FEFF, U+FFFE
 *        *   - U+FFFF
 *         * These code points are excluded because they are likely to
 *          * arise through misconversions and may cause problems.
 *           * There are lots of other weird UCS characters which would not
 *            * be exactly welcome in a DB entry, but it's best to keep the
 *             * rule simple and consistent.
 *              */
#define GOOD_ASCII(c) \
	((32 <= (c) && (c) < 127) || \
	 (c) == 9 || (c) == 10 || (c) == 13)
#define GOOD_UCS(c) \
	(GOOD_ASCII(c) || \
	 ((c) >= 160 && ((c) & ~0x7ff) != 0xd800 && \
	  (c) != 0xfeff && (c) != 0xfffe && (c) != 0xffff))
#define GOOD_LATIN1(c) \
	(GOOD_ASCII(c) || \
	 ((c) >= 160))

int charset_is_valid_utf8(const char *s)
{
	const char *t = s;
	int n;

	while ((n = parse_utf8(&t))) {
		if (!GOOD_UCS(n))
			return 0;
	}
	return 1;
}

int charset_is_valid_ascii(const char *s)
{
	for (; *s; s++) {
		if (!GOOD_ASCII(*s))
			return 0;
	}
	return 1;
}

int charset_is_valid_latin1(const char *s)
{
	for (; *s; s++) {
		if (!GOOD_LATIN1((unsigned char)*s))
			return 0;
	}
	return 1;
}

regex_t regex_dtitle;
regex_t regex_ttitle;

int islatin1 = 1;
int isutf8 = 1;
int isascii7 = 1;

void pgquote(char**s, int multiline)
{
    char * res;
    char * res1;
    int i, j = 0, k = 0;
    if (!*s)
    {
      *s = strdup("\\N");
      return;
    }
    for (res = *s; ' ' == res[0]; res++)
      ;
    while(res[0] && res[strlen(res)-1] == ' ')
      res[strlen(res)-1] = 0;
    if (!*res)
    {
      free(*s);
      *s = strdup("\\N");
      return;
    }
    islatin1 &= charset_is_valid_latin1(res);
    isascii7 &= charset_is_valid_ascii(res);
    isutf8 &= charset_is_valid_utf8(res);

    if (res == *s && !strpbrk(res, "\\\t\r\n"))
      return;

    res = malloc(strlen(*s) * 2 + 10);
    res1 = malloc(strlen(*s) * 2 + 10);
    int fquote = 0;
    for (i = 0; i < strlen(*s); i++)
    {
      char c = (*s)[i];
      if (fquote) {
	switch(c) {
	  case 't': res[j++] = '\t'; break;
//	  case 'r': res[j++] = '\r'; break;
	  case 'n': res[j++] = multiline ? '\n' : ' '; break;
	  case '\\': res[j++] = '\\'; break;
	  case '\t': break;
	  case '\r': break;
	  default:
            //fprintf(stderr, "%s/%08x: Invalid escape sequence \\%u\n", validcategories[category], freedbid, (unsigned char)c);
            res[j++] = '\\';
            res[j++] = c;
	    break;
	}
	fquote = 0;
      } else if (c == '\\')
	fquote = 1;
       else
        res[j++] = c;
    }
    if (fquote) {
      //fprintf(stderr, "%s/%08x: Invalid escape sequence\n", validcategories[category], freedbid);
      res[j++] = '\\';
    }
    while (j > 0 && strchr(" \r\t\n", res[j-1]))
      j--;
    for (i = 0; i < j && strchr(" \r\t\n", res[i]); i++)
      ;
    for (; i < j; i++) {
      char c = res[i];
      switch(c) {
        case '\t': res1[k++] = '\\'; res1[k++] = 't'; break;
        case '\r': res1[k++] = '\\'; res1[k++] = 'r'; break;
        case '\n': res1[k++] = '\\'; res1[k++] = 'n'; break;
        case '\\': res1[k++] = '\\'; res1[k++] = '\\'; break;
	default: res1[k++] = c; break;
      }
    }
    res1[k++] = 0;
    free(*s);
    free(res);
    *s = res1;
}

void charset_latin1_utf8(char **ps)
{
	char *s = *ps;
	char *buf, *p;

	p = buf = xrealloc(0, strlen(s) * 2 + 1);
	for (; *s; s++)
		if ((*s & 0x80) == 0)
			*p++ = *s;
		else {
			*p++ = 0xc0 | ((*s >> 6) & 0x03);
			*p++ = 0x80 | (*s & 0x3f);
		}
	*p++ = '\0';
	free(*ps);
	*ps = xrealloc(buf, p - buf);
}

int lookup(struct hsearch_data *table, char*key, int*maxid, int*id)
{
  ENTRY entry;
  ENTRY* ret = NULL;
  entry.key = key;
  if (hsearch_r(entry, FIND, &ret, table) == 0) {
    entry.key = strdup(key);
    entry.data = (void*) ++*maxid;
    if (!hsearch_r(entry, ENTER, &ret, table)) { fprintf(stderr, "hash table overflow: %d\n", *maxid); exit(2); }
    *id = (int)ret->data;
    return 0;
  }
  *id = (int)ret->data;
  return 1;
}

void output()
{
    static int entryid = 0;
    static int max_genre_name_id = 0;
    static int max_artist_name_id = 0;
    static int max_track_name_id = 0;
//    static int max_track_extra_id = 0;
    regmatch_t res[3];
    char *dalbum = 0;
    char *dartist = 0;
    int i;
    char buf_artist[16] = "\\N";
    char buf_genre[16] = "\\N";
    char buf_year[16] = "\\N";
    char* t_artist[100];
    char* t_title[100];
    int va = -1;
    int num = -1;
    char buf_offsets[100*7];
    int i_offsets = 0;

    for (i = 0; i < c_title; i++)
      num &= ttitle[i] && 0 == regexec(&regex_ttitle, ttitle[i], 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0 && atoi(ttitle[i]) == i + 1;
    if (num) for (i = 0; i < c_title; i++) {
      regexec(&regex_ttitle, ttitle[i], 3, res, 0);
      memmove(ttitle[i], ttitle[i] + res[2].rm_so, strlen(ttitle[i] + res[2].rm_so) + 1);
    }

    pgquote(&dtitle, 0);
    pgquote(&dgenre, 0);
    pgquote(&dext, 1);
    for (i = 0; i < c_title; i++)
      pgquote(ttitle + i, 0);
    for (i = 0; i < c_ext; i++)
      pgquote(t_ext + i, 1);
    if (!isascii7 && !isutf8 && !islatin1)
    {
       fprintf(stderr, "Invalid encoding for %s/%08x\n", validcategories[category], freedbid);
       return;
    }
    if (!isascii7 && !isutf8 && islatin1)
    {
        charset_latin1_utf8(&dtitle);
        charset_latin1_utf8(&dgenre);
        charset_latin1_utf8(&dext);
        for (i = 0; i < c_title; i++)
          charset_latin1_utf8(ttitle + i);
        for (i = 0; i < c_ext; i++)
          charset_latin1_utf8(t_ext + i);
    }
    if (0 == regexec(&regex_dtitle, dtitle, 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0) {
	dtitle[res[1].rm_eo] = 0;
	dartist = dtitle;
        dalbum = dtitle + res[2].rm_so;
    } else {
	dartist = "\\N";
        dalbum = dtitle;
    }
    if (strcmp(dgenre, "\\N")) {
      int genre_name_id;
      if (!lookup(&h_genre_names, dgenre, &max_genre_name_id, &genre_name_id))
        BZ2_bzPrintf(genre_names, "%d\t%s\n", genre_name_id, dgenre);
      snprintf(buf_genre, 16, "%d", genre_name_id);
    }
    if (strcmp(dartist, "\\N")) {
      int artist_name_id;
      if (!lookup(&h_artist_names, dartist, &max_artist_name_id, &artist_name_id))
        BZ2_bzPrintf(artist_names, "%d\t%s\n", artist_name_id, dartist);
      snprintf(buf_artist, 16, "%d", artist_name_id);
    }
    if (dyear && atoi(dyear) > 0)
      snprintf(buf_year, 16, "%d", atoi(dyear));
    for (i = 0; i < n_offsets; i++)
      i_offsets += snprintf(buf_offsets + i_offsets, sizeof(buf_offsets) - i_offsets, ",%d", offsets[i]);
    buf_offsets[i_offsets] = 0;
    BZ2_bzPrintf(entries, "%d\t%d\t%s\t%s\t%s\t%s\t%s\t%s\t{%s,%d}\n", ++entryid, freedbid, validcategories[category], buf_year, buf_genre, buf_artist, dalbum, dext, buf_offsets + 1, disc_length * 75);
//    if (strcmp(dext, "\\N"))
//      BZ2_bzPrintf(disc_extra, "%d\t%s\n", entryid, dext);
    for (i = 0; i < c_title; i++) {
      t_artist[i] = "\\N";
      t_title[i] = ttitle[i];
      va &= 0 == regexec(&regex_dtitle, ttitle[i], 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0;
    }
    if (va) for (i = 0; i < c_title; i++) {
      regexec(&regex_dtitle, ttitle[i], 3, res, 0);
      ttitle[i][res[1].rm_eo] = 0;
      t_artist[i] = ttitle[i];
      t_title[i] = ttitle[i] + res[2].rm_so;
    }
    for (i = 0; i < n_offsets; i++) {
      char * track_name = i < c_title ? t_title[i] : "\\N";
      char * track_extra = i < c_ext ? t_ext[i] : "\\N";
//      int track_extra_id = 0;
      int artist_name_id = 0;
      if (i < c_title && strcmp(t_artist[i], "\\N"))
	if (!lookup(&h_artist_names, t_artist[i], &max_artist_name_id, &artist_name_id))
          BZ2_bzPrintf(artist_names, "%d\t%s\n", artist_name_id, t_artist[i]);
//      if (i < c_title && strcmp(t_title[i], "\\N"))
//	if (!lookup(&h_track_names, t_title[i], &max_track_name_id, &track_name_id))
//          BZ2_bzPrintf(track_names, "%d\t%s\n", track_name_id, t_title[i]);
//      if (i < c_ext && strcmp(t_ext[i], "\\N"))
//	if (!lookup(&h_track_extra, t_ext[i], &max_track_extra_id, &track_extra_id))
//          BZ2_bzPrintf(track_extra, "%d\t%s\n", track_extra_id, t_ext[i]);
      if (strcmp(track_name,"\\N") || strcmp(track_extra,"\\N") || artist_name_id) {
//	char buf1[16] = "\\N";
//	char buf2[16] = "\\N";
	char buf3[16] = "\\N";
//        if (track_name_id) snprintf(buf1, 16, "%d", track_name_id);
//        if (track_extra_id) snprintf(buf2, 16, "%d", track_extra_id);
        if (artist_name_id) snprintf(buf3, 16, "%d", artist_name_id);
        BZ2_bzPrintf(tracks, "%d\t%d\t%s\t%s\t%s\n", entryid, i+1, buf3, track_extra, track_name);
      }
    }
}

void append(char**var, char *name, char *buf, regmatch_t* match)
{
    if (match[1].rm_eo - match[1].rm_so == strlen(name) && !strncmp(buf + match[1].rm_so, name, match[1].rm_eo - match[1].rm_so))
    {
        if (!*var)
            *var = strdup(buf + match[2].rm_so);
        else {
            char *tmp = (char*)malloc(strlen(*var) + strlen(buf + match[2].rm_so) + 1);
            strcpy(tmp, *var);
            strcpy(tmp + strlen(*var), buf + match[2].rm_so);
	    free(*var);
            *var = tmp;
        }
        //printf("%s == %s\n", name, *var);
    }
}

void append1(char**var, int*count, char *name, char *buf, regmatch_t* match)
{
    if (match[1].rm_eo - match[1].rm_so == strlen(name) && !strncmp(buf + match[1].rm_so, name, match[1].rm_eo - match[1].rm_so))
    {
        long n = strtol(buf + match[2].rm_so, NULL, 10);
        if (n < 0 || n > 99) {
            fprintf(stderr, "Invalid track no\n");
            exit(1);
        }
        if (*count <= n)
          *count = n+1;
        if (!var[n])
            var[n] = strdup(buf + match[3].rm_so);
        else {
            char *tmp = (char*)malloc(strlen(var[n]) + strlen(buf + match[3].rm_so) + 1);
            strcpy(tmp, var[n]);
            strcpy(tmp + strlen(var[n]), buf + match[3].rm_so);
	    free(var[n]);
            var[n] = tmp;
        }
        //printf("%s[%ld] == %s\n", name, n, var[n]);
    }
}

void compile(regex_t* r, char *p, int flags)
{
    int err_no=0;
    if((err_no=regcomp(r, p, flags))!=0)
    {
      char buf[1024];
      regerror (err_no, r, buf, sizeof(buf));
      fprintf(stderr, "%s\n", buf);
      exit(1);
    }
}

int main(void)
{
    char buf[1024];
    int state = 0;
    regex_t regex_id, regex_offsets, regex_offset, regex_length, regex_entry, regex_entry1;
    regmatch_t res[10];
    int empty = 1;

//    hcreate_r(30000000, &h_track_names); 
    hcreate_r(200000, &h_genre_names); 
    hcreate_r(4000000, &h_artist_names); 
//    hcreate_r(900000, &h_track_extra); 

    entries = BZ2_bzWriteOpen(&bzerror, fopen("freedb_entries.sql.bz2", "wb"), 1, 0, 0);
//    disc_extra = BZ2_bzWriteOpen(&bzerror, fopen("freedb_disc_extra.sql.bz2", "wb"), 1, 0, 0);
    tracks = BZ2_bzWriteOpen(&bzerror, fopen("freedb_tracks.sql.bz2", "wb"), 1, 0, 0);
    genre_names = BZ2_bzWriteOpen(&bzerror, fopen("freedb_genre_names.sql.bz2", "wb"), 1, 0, 0);
    artist_names = BZ2_bzWriteOpen(&bzerror, fopen("freedb_artist_names.sql.bz2", "wb"), 1, 0, 0);
//    track_names = BZ2_bzWriteOpen(&bzerror, fopen("freedb_track_names.sql.bz2", "wb"), 1, 0, 0);
//    track_extra = BZ2_bzWriteOpen(&bzerror, fopen("freedb_track_extra.sql.bz2", "wb"), 1, 0, 0);

    BZ2_bzPrintf(entries, "COPY entries (id, freedbid, category, year, genre, artist, title, extra, offsets) FROM stdin;\n");
//    BZ2_bzPrintf(disc_extra, "COPY disc_extra (id, extra) FROM stdin;\n");
    BZ2_bzPrintf(tracks, "COPY tracks (id, number, artist, extra, title) FROM stdin;\n");
    BZ2_bzPrintf(genre_names, "COPY genre_names (id, name) FROM stdin;\n");
    BZ2_bzPrintf(artist_names, "COPY artist_names (id, name) FROM stdin;\n");
//    BZ2_bzPrintf(track_names, "COPY track_names (id, name) FROM stdin;\n");
//    BZ2_bzPrintf(track_extra, "COPY track_extra (id, extra) FROM stdin;\n");

    compile(&regex_id, "^([a-z]{1,12})/([0-9a-f]{8})$", REG_EXTENDED);
    compile(&regex_offsets, "^#[ \t]*Track frame offsets:[ \t]*$", REG_EXTENDED);
    compile(&regex_offset, "^#[ \t]*([0-9]+)[ \t]*$", REG_EXTENDED);
    compile(&regex_length, "^#[ \t]*Disc length: ([0-9]+)( seconds){0,1}[ \t]*$", REG_EXTENDED);
    compile(&regex_entry, "^([A-Z]+)=(.+)$", REG_EXTENDED);
    compile(&regex_entry1, "^([A-Z]+)([0-9]+)=(.+)$", REG_EXTENDED);
    compile(&regex_dtitle, "^(.*) +/ +(.*)$", REG_EXTENDED);
    compile(&regex_ttitle, "^([0-9]+)[-. /]*(.*)$", REG_EXTENDED);
    
    while (fgets(buf, sizeof(buf), stdin)) 
    {
        if (strlen(buf) >= sizeof(buf) - 1) {
            fprintf(stderr,"Line too long\n");
	    exit(1);
        }
        if (strlen(buf) == 0) {
            fprintf(stderr,"Empty line\n");
	    exit(1);
        }
        if (buf[strlen(buf) - 1] != '\n') { 
            fprintf(stderr,"No new line character\n");
	    exit(1);
        }
        buf[strlen(buf) - 1] = 0;
        if (0 == regexec(&regex_id, buf, 3, res, 0)) {
          int i;
	  if (category >= 0 && !empty) output();
	  //if (category >= 0 && empty) fprintf(stderr,"skip symlink\n");
	  free(dtitle); free(dyear); free(dgenre); free(dext);
          for (i = 0; i < c_title; i++) {
              free(ttitle[i]);
              ttitle[i] = 0;
          }
          for (i = 0; i < c_ext; i++) {
              free(t_ext[i]);
              t_ext[i] = 0;
          }
          c_title = c_ext = 0;
	  empty = 1;
          isascii7 = islatin1 = isutf8 = 1;
          dtitle = dyear = dgenre = dext = 0;
          freedbid = (int)strtoul(buf + res[2].rm_so, NULL, 16);
          for (i = 0; validcategories[i]; i++)
	    if (strlen(validcategories[i]) == res[1].rm_eo - res[1].rm_so && !strncmp(validcategories[i], buf + res[1].rm_so, res[1].rm_eo - res[1].rm_so)) {
	      category = i;
	      break;
            }
	  if (!validcategories[i]) {
            fprintf(stderr,"Invalid category %s\n", buf + res[1].rm_so);
	    exit(1);
          }
          disc_length = 0;
          n_offsets = 0;
          state = 0;
	  continue;
        }
	empty = 0;
        if (0 == state && 0 == regexec(&regex_offsets, buf, 1, res, 0)) {
          state = 1;
          continue;
        }
        if (1 == state && 0 == regexec(&regex_offset, buf, 1, res, 0)) {
          offsets[n_offsets++] = atoi(buf+1);
          continue;
        }
        if (1 == state && 0 == regexec(&regex_length, buf, 2, res, 0) && res[1].rm_so >= 0) {
          disc_length = strtol(buf + res[1].rm_so, NULL, 10);
          state = 2;
          continue;
        }
        if (2 == state && 0 == regexec(&regex_entry, buf, 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0) {
	  append(&dtitle, "DTITLE", buf, res);
	  append(&dyear, "DYEAR", buf, res);
	  append(&dgenre, "DGENRE", buf, res);
	  append(&dext, "EXTD", buf, res);
          continue;
        }
        if (2 == state && 0 == regexec(&regex_entry1, buf, 4, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0 && res[3].rm_so >= 0) {
	  append1(ttitle, &c_title, "TTITLE", buf, res);
	  append1(t_ext, &c_ext, "EXTT", buf, res);
          continue;
        }
    }
    if (category >= 0) output();

    BZ2_bzPrintf(entries, "\\.\n");
//    BZ2_bzPrintf(disc_extra, "\\.\n");
    BZ2_bzPrintf(tracks, "\\.\n");
    BZ2_bzPrintf(genre_names, "\\.\n");
    BZ2_bzPrintf(artist_names, "\\.\n");
//    BZ2_bzPrintf(track_names, "\\.\n");
//    BZ2_bzPrintf(track_extra, "\\.\n");
    BZ2_bzWriteClose(&bzerror, entries, 0, NULL, NULL);
//    BZ2_bzWriteClose(&bzerror, disc_extra, 0, NULL, NULL);
    BZ2_bzWriteClose(&bzerror, tracks, 0, NULL, NULL);
    BZ2_bzWriteClose(&bzerror, genre_names, 0, NULL, NULL);
    BZ2_bzWriteClose(&bzerror, artist_names, 0, NULL, NULL);
//    BZ2_bzWriteClose(&bzerror, track_names, 0, NULL, NULL);
//    BZ2_bzWriteClose(&bzerror, track_extra, 0, NULL, NULL);
}
