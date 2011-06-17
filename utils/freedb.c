#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <regex.h> /* Provides regular expression matching */

char *id = NULL;
long disc_length = 0;
int offsets[100];
int n_offsets = 0;
char *dtitle = 0;
char *dyear = 0;
char* ttitle[100];
int tracks = 0;

regex_t regex_dtitle;

void pgquote(char**s)
{
    char * res;
    int i, j = 0;
    if (!*s)
    {
        *s = strdup("NULL");
        return;
    } 
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

void output()
{
    regmatch_t res[3];
    char *dalbum = 0;
    char *dartist = 0;
    int i;
    if (dtitle && 0 == regexec(&regex_dtitle, dtitle, 3, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0)
    {
        dartist = strndup(dtitle, res[1].rm_eo);
        dalbum = strndup(dtitle + res[2].rm_so, res[2].rm_eo - res[2].rm_so);
    }
    else if (dtitle)
        dalbum = strdup(dtitle);
    pgquote(&dartist);
    pgquote(&dalbum);
    printf("INSERT INTO entries VALUES ('%s', %d, ARRAY[%d", id, disc_length, offsets[0]);
    for (i = 1; i < n_offsets; i++)
      printf(",%d", offsets[i]);
    printf("], %s, %s, %s, ",dyear && strcmp(dyear,"0") ? dyear : "NULL", dartist, dalbum);
    if (!tracks)
      printf("NULL");
    else {
      for (i = 0; i < tracks; i++)
        pgquote(ttitle + i);
      printf("ARRAY[%s", ttitle[0]);
      for (i = 1; i < tracks; i++)
        printf(",%s", ttitle[i]);
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

void append1(char**var, char *name, char *buf, regmatch_t* match)
{
    if (match[1].rm_eo - match[1].rm_so == strlen(name) && !strncmp(buf + match[1].rm_so, name, match[1].rm_eo - match[1].rm_so))
    {
        long n = strtol(buf + match[2].rm_so, NULL, 10);
        if (n < 0 || n > 99) {
            fprintf(stderr, "Invalid track no\n");
            exit(1);
        }
        if (tracks <= n)
          tracks = n+1;
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

    compile(&regex_id, "^[a-z]{1,12}/[0-9a-f]{8}$", REG_EXTENDED);
    compile(&regex_offsets, "^#[ \t]*Track frame offsets:[ \t]*$", REG_EXTENDED);
    compile(&regex_offset, "^#[ \t]*([0-9]+)[ \t]*$", REG_EXTENDED);
    compile(&regex_length, "^#[ \t]*Disc length: ([0-9]+)( seconds){0,1}[ \t]*$", REG_EXTENDED);
    compile(&regex_entry, "^([A-Z]+)=(.+)$", REG_EXTENDED);
    compile(&regex_entry1, "^([A-Z]+)([0-9]+)=(.+)$", REG_EXTENDED);
    compile(&regex_dtitle, "^(.*) / (.*)$", REG_EXTENDED);
    
    printf("set client_encoding TO latin1;\n");

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
        if (0 == regexec(&regex_id, buf, 1, res, 0)) {
          int i;
	  if (id) output();
	  free(id); free(dtitle); free(dyear);
          for (i = 0; i < tracks; i++) {
              free(ttitle[i]);
              ttitle[i] = 0;
          }
          tracks = 0;
          dtitle = dyear = 0;
          id = strdup(buf);
          disc_length = 0;
          n_offsets = 0;
          state = 0;
	  continue;
        }
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
          continue;
        }
        if (2 == state && 0 == regexec(&regex_entry1, buf, 4, res, 0) && res[1].rm_so >= 0 && res[2].rm_so >= 0 && res[3].rm_so >= 0) {
	  append1(ttitle, "TTITLE", buf, res);
          continue;
        }
    }
    if (id) output();
}
