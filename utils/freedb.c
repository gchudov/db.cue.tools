#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <regex.h> /* Provides regular expression matching */


// TODO: handle \\ and \n somewhere here!
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

char * validcategories[] = {"blues","classical","country",
	"data","folk","jazz","misc",
	"newage","reggae","rock","soundtrack"};

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

void charset_latin1_utf8(const char *s, char **ps)
{
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
	*ps = xrealloc(buf, p - buf);
}
				
int charset_is_utf8(const char *s)
{
	const char *t = s;
	int n;

	while ((n = parse_utf8(&t)))
		if (n == -1)
			return 0;
	return 1;
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
	 ((c) >= 160 && ((c) & ~0x3ff) != 0xd800 && \
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

int islatin1 = 1;
int isutf8 = 1;
int isascii7 = 1;

void pgquote(char**s)
{
    char * res;
    int i, j = 0;
    if (!*s || !**s)
    {
        *s = strdup("NULL");
        return;
    }
    islatin1 &= charset_is_valid_latin1(*s);
    isascii7 &= charset_is_valid_ascii(*s);
    isutf8 &= charset_is_valid_utf8(*s);
    //if (!charset_is_valid_ascii(*s) && !charset_is_valid_utf8(*s) && charset_is_valid_latin1(*s)) {
	//char *u = 0;
	//charset_latin1_utf8(*s, &u);
	//free(*s);
	//*s = u;
    //}
    res = malloc(strlen(*s) * 2 + 10);
    res[j++] = 'E';
    res[j++] = '\'';
    for (i = 0; i < strlen(*s); i++)
    {
      char c = (*s)[i];
      if (c == '\'' || c == '\\')
        res[j++] = '\\';
      res[j++] = c;
    }
    res[j++] = '\'';
    res[j++] = 0;
    free(*s);
    *s = res;
}

int waslatin = 0;

void output()
{
    regmatch_t res[3];
    char *dalbum = 0;
    char *dartist = 0;
    int i;
    int needlatin;
    if (dtitle && 0 == regexec(&regex_dtitle, dtitle, 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0)
    {
        dartist = strndup(dtitle, res[1].rm_eo);
        dalbum = strndup(dtitle + res[2].rm_so, res[2].rm_eo - res[2].rm_so);
    }
    else if (dtitle)
        dalbum = strdup(dtitle);
    pgquote(&dartist);
    pgquote(&dalbum);
    pgquote(&dgenre);
    pgquote(&dext);
    for (i = 0; i < c_title; i++)
      pgquote(ttitle + i);
    for (i = 0; i < c_ext; i++)
      pgquote(t_ext + i);
    needlatin = !isascii7 && !isutf8 && islatin1;
    if (!waslatin && needlatin)
      printf("set client_encoding TO latin1;\n");
    if (waslatin && !needlatin)
      printf("set client_encoding TO utf8;\n");
    waslatin = needlatin;
    printf("INSERT INTO entries VALUES (%d, %d, %d, ARRAY[%d", freedbid, category, disc_length, offsets[0]);
    for (i = 1; i < n_offsets; i++)
      printf(",%d", offsets[i]);
    printf("], %s, %s, %s, %s, %s, ",dyear && strcmp(dyear,"0") ? dyear : "NULL", dartist, dalbum, dgenre, dext);
    if (!c_title)
      printf("NULL, ");
    else {
      printf("ARRAY[%s", ttitle[0]);
      for (i = 1; i < c_title; i++)
        printf(",%s", ttitle[i]);
      printf("], ");
    }
    if (!c_ext)
      printf("NULL");
    else {
      printf("ARRAY[%s", t_ext[0]);
      for (i = 1; i < c_ext; i++)
        printf(",%s", t_ext[i]);
      printf("]");
    }
    printf(");\n");
    free(dartist); free(dalbum);
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

    compile(&regex_id, "^([a-z]{1,12})/([0-9a-f]{8})$", REG_EXTENDED);
    compile(&regex_offsets, "^#[ \t]*Track frame offsets:[ \t]*$", REG_EXTENDED);
    compile(&regex_offset, "^#[ \t]*([0-9]+)[ \t]*$", REG_EXTENDED);
    compile(&regex_length, "^#[ \t]*Disc length: ([0-9]+)( seconds){0,1}[ \t]*$", REG_EXTENDED);
    compile(&regex_entry, "^([A-Z]+)=(.+)$", REG_EXTENDED);
    compile(&regex_entry1, "^([A-Z]+)([0-9]+)=(.+)$", REG_EXTENDED);
    compile(&regex_dtitle, "^(.*) / (.*)$", REG_EXTENDED);
    
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
}
