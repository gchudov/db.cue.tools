/* CTDB */

function str_pad(num, len, pad_string, pad_type)
{
  var str_pad_repeater = function (s, len) {
    var collect = ''; 
    while (collect.length < len)
      collect += s;
    return collect.substr(0, len); 
  };
  num = String(num);
  if (num.length >= len) return num;
  if (pad_type != 'STR_PAD_LEFT' && pad_type != 'STR_PAD_RIGHT' && pad_type != 'STR_PAD_BOTH')
      pad_type = 'STR_PAD_RIGHT';
  pad_string = pad_string !== undefined ? pad_string : ' ';
  var pad = str_pad_repeater(pad_string, len -  num.length);
  return pad_type == 'STR_PAD_LEFT' ? pad + num : num + pad;
}

function str_pad_num(num, len)
{
  return str_pad(num, len, "0", "STR_PAD_LEFT");
}

function decimalToHexString(number)
{
  var hex = (number < 0 ?  0xFFFFFFFF + number + 1 : number).toString(16).toUpperCase();
  return str_pad_num(hex, 8);
};

function pad2(n)
{
  return str_pad_num(n, 2);
}

function TimeToString(sector) {
  var frame = sector % 75;
  sector = Math.floor(sector / 75);
  var sec = sector % 60;
  sector = Math.floor(sector / 60);
  var min = sector;
  return min + ':' + pad2(sec) + '.' + pad2(frame);
}

function ctdbVideos(vidlist, vis)
{
  var html = '';
  var vidfound = new Object;
  var vidfoundlen = 0;
  for (var ivid in vidlist) {
    var vid = vidlist[ivid].uri;
    if (vid in vidfound) continue;
    vidfound[vid] = 1;
    vidfoundlen ++;
    if (vidfoundlen > vis)
      html += '<span style="display:none;">';
    var yid = vid.substr(31);
    html += '<a class="thumbnail" title="' + vidlist[ivid].title + '" href="http://www.youtube.com/v/' + yid + '&hl=en&fs=1&rel=0&autoplay=1" rel="shadowbox[vids];height=480;width=700;player=swf">';
    if (vidfoundlen > vis)
      html += ' </a></span>';
    else
      html += '<img src="http://i.ytimg.com/vi/' + yid + '/default.jpg" class="coverart-thumbnail"></a>';
  }
  return html;
}

function ctdbCoverart(imglist,primary,vis)
{
  var html = '';
  var imgfound = new Object;
  var imgfoundlen = 0;
  for (var prim = 1; prim <= (primary ? 1 : 2); prim++)
  {
  for (var iimg in imglist) {
    if (prim != (imglist[iimg].primary ? 1 : 2)) continue;
    var img = imglist[iimg].uri;
    if (img.indexOf('http://api.discogs.com/') != -1) img = imglist[iimg].uri150;
    if (img.indexOf('http://images.amazon.com/') != -1) continue;
    if (img in imgfound) continue;
    imgfound[img] = 1;
    imgfoundlen ++;
    if (imgfoundlen > vis)
      html += '<span style="display:none;">';
    var sz = '';
    if (img == imglist[iimg].uri) {
      if ('height' in imglist[iimg]) sz += ";height=" + imglist[iimg].height;
      if ('width' in imglist[iimg]) sz += ";width=" + imglist[iimg].width;
    }
    html += '<a class="thumbnail" href="' + img + '" rel="shadowbox[covers];player=img' + sz + '">';
    if (imgfoundlen > vis)
      html += ' </a></span>';
    else {
      var source = null;
      if (img.indexOf('http://api.discogs.com/') != -1) source = 'discogs';
      if (img.indexOf('http://coverartarchive.org/') != -1) source = 'musicbrainz';
      html += '<img src="' + imglist[iimg].uri150 + '" class="coverart-thumbnail">' + (source == null ? '' : '<span class="coverart-source"><img src="http://s3.cuetools.net/icons/' + source + '.png" width=16 height=16 border=0 alt="' + source + '"></span>') + '</a>';
    }
  }
  }
  return html;
}

function ctdbEntryData(json)
{
  var data = new google.visualization.DataTable(json);
  for (var row = 0; row < data.getNumberOfRows(); row++) {
    var artist = data.getValue(row, 0);
    if (!artist) artist = "Unknown Artist";
    data.setFormattedValue(row, 0, '<a href="?artist=' + encodeURIComponent(artist) + '">' + artist.substring(0,50) + '</a>');
    var title = data.getValue(row, 1);
    if (!title) title = "Unknown Title";
    data.setFormattedValue(row, 1, title.substring(0,60));
    var toc = data.getValue(row, 2);
    data.setFormattedValue(row, 2, '<a href="?tocid=' + toc + '">' + toc + '</a>');
    data.setFormattedValue(row, 4, '<a href="/cd/' + data.getValue(row, 4).toString(10) + '">' + decimalToHexString(data.getValue(row, 6)) + '</a>');
    data.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-nowrap');
    data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
  }
  return data;
};

function ctdbMetaData(json)
{
  var mbdata = new google.visualization.DataTable(json);
  for (var row = 0; row < mbdata.getNumberOfRows(); row++) {
    mbdata.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    mbdata.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    mbdata.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-nowrap');
    mbdata.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    mbdata.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    mbdata.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    var title = $('<div/>').text(mbdata.getValue(row, 2)).html();
    if (mbdata.getValue(row, 8) == 'musicbrainz')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-musicbrainz"><a target=_blank href="http://musicbrainz.org/release/' + mbdata.getValue(row, 7) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 8) == 'cdstub')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-cdstub"><a target=_blank href="http://musicbrainz.org/cdstub/' + mbdata.getValue(row, 7) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 8) == 'discogs')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-discogs"><a target=_blank href="http://www.discogs.com/release/' + mbdata.getValue(row, 7) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 8) == 'freedb')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-freedb"><a target=_blank href="http://www.freedb.org/freedb/' + mbdata.getValue(row, 7) + '">' + title + '</a></div>');
//          $label = $label . ($label != '' ? ', ' : '') . $l['name'] . (@$l['catno'] ? ' ' . $l['catno'] : '');
    var releases = mbdata.getValue(row, 4);
    if (releases != null) {
      var v = '';
      var datefound = false;
      for (var r in releases) {
        if (!datefound && releases[r].date != null && mbdata.getValue(row, 0) != null) { 
//          v = releases[r].date + ' ' + v;
          var y = mbdata.getValue(row, 0).toString();
          if (releases[r].date.substring(0, y.length) == y)
            mbdata.setFormattedValue(row, 0, releases[r].date);
          else
            mbdata.setFormattedValue(row, 0, '(' + y + ') ' + releases[r].date);
          datefound = true;
        }
        var flags = new Array('us','gb','xe');
        var country = releases[r].country != null ? releases[r].country.toLowerCase() : 'unknown';
        var flagno = flags.indexOf(country);
        if (flagno < 0)
          v = v + '<div title="' + country + ": " + releases[r].date + '" style="padding: 0; width: 20px; display: inline-block; height: 11px; background: url(&quot;http://s3.cuetools.net/flags/' + country + '.png&quot;) no-repeat scroll 0 0 transparent"></div>';
        else
          v = v + '<div title="' + country + ": " + releases[r].date + '" style="padding: 0; width: 20px; display: inline-block; height: 11px; background: url(&quot;http://s3.cuetools.net/flags/flags.png?id=2&quot;) no-repeat scroll 0 -' + flagno * 11 + 'px transparent"></div>';
      }
      mbdata.setFormattedValue(row, 4, v);
    }
    var labels = mbdata.getValue(row, 5);
    if (labels != null) {
      var v = '';
      for (var r in labels) {
          v = v + (v != '' ? ', ' : '') + labels[r].name + (labels[r].catno != null ? ' ' + labels[r].catno : '');
      }
      mbdata.setFormattedValue(row, 5, v.substring(0, 30));
    }
    mbdata.setProperty(row, 6, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    mbdata.setProperty(row, 9, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    if (mbdata.getValue(row,9) != null) {
      var diff = 100 - mbdata.getValue(row,9);
      color = (255 - diff).toString(16).toUpperCase() + (255 - Math.floor(diff*0.7)).toString(16).toUpperCase() + "FF";
      mbdata.setProperty(row, 9, 'style', 'background-color:#' + color + ';');
    //mbdata.setFormattedValue(row, 9, '<span style="background-color:#' + color + ';">' + mbdata.getValue(row,9) + '</span>');
    }
  }
  return mbdata;
};

function ctdbSubmissionData(json)
{
  var data = new google.visualization.DataTable(json);
  for (var row = 0; row < data.getNumberOfRows(); row++) {
    var dt = new Date(data.getValue(row, 0)*1000);
    var dtnow = new Date();
    var dtstring = (dtnow - dt > 1000*60*60*24 ? dt.getFullYear()
      + '-' + pad2(dt.getMonth()+1)
      + '-' + pad2(dt.getDate())
      + ' ' : '') + pad2(dt.getHours())
      + ':' + pad2(dt.getMinutes())
      + ':' + pad2(dt.getSeconds());
    data.setFormattedValue(row, 0, dtstring);
    data.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var matches = data.getValue(row, 1).match(/(CUETools|CUERipper|EACv.* CTDB) ([\d\.]*)/);
    var imgstyle = 'ctdb-entry-' + (matches == null ? 'unknown' : matches[1] == 'CUETools' ? 'cuetools' :  matches[1] == 'CUERipper' ? 'cueripper' : matches[1].indexOf('EACv1.0') == 0 ? 'eac' : 'unknown'); 
    data.setFormattedValue(row, 1, '<div class="' + imgstyle + '"><a href="?agent=' + data.getValue(row, 1) + '">' + (matches == null ? '?' : matches[2]) + '</a></div>');

    data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas-left');
    matches = data.getValue(row, 2).match(/(hl-dt-st|tsstcorp|plextor|hp|asus|pioneer|matshita|creative|_nec|benq|sony|optiarc|lite-on|slimtype|atapi|plds).* - *(.*)/i);
    var driveIcon = matches == null ? null : matches[1].toLowerCase();
    if (driveIcon != null)
      data.setFormattedValue(row, 2, '<span style="padding-left:18px; background:url(&quot;http://s3.cuetools.net/icons/' + driveIcon + '.png&quot;) no-repeat scroll 0px 50% transparent;"></span><a href="?drivename=' + encodeURIComponent(data.getValue(row, 2)) + '">' + matches[2].substring(0,20) + '</a>');
    else
      data.setFormattedValue(row, 2, '<span style="padding-left:18px"> </span><a href="?drivename=' + encodeURIComponent(data.getValue(row, 2)) + '">' + data.getValue(row, 2).substring(0,20) + '</a>');
    data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 3, '<a href="?uid=' + data.getValue(row, 3) + '">' + data.getValue(row, 3).substring(0,6) + '</a>');
    data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas-left');
    var artist = data.getValue(row, 5);
    if (!artist) artist = "Unknown Artist";
    data.setFormattedValue(row, 5, '<a href="?artist=' + encodeURIComponent(artist) + '">' + artist.substring(0,30) + '</a>');
    var title = data.getValue(row, 6);
    if (!title) title = "Unknown Title";
    data.setFormattedValue(row, 6, title.substring(0,30));
    data.setProperty(row, 7, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var toc = data.getValue(row, 7);
    data.setFormattedValue(row, 7, '<a href="?tocid=' + toc + '">' + toc.substring(0,7) + '</a>');
    data.setProperty(row, 8, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 9, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 9, '<a href="/cd/' + data.getValue(row, 9).toString(10) + '">' + decimalToHexString(data.getValue(row, 11)) + '</a>');
    data.setProperty(row, 10, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 13, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 14, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
  }
  return data;
};

function sha1 (str, binary) {
    // http://kevin.vanzonneveld.net
    // +   original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // + namespaced by: Michael White (http://getsprink.com)
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // -    depends on: utf8_encode
    // *     example 1: sha1('Kevin van Zonneveld');
    // *     returns 1: '54916d2e62f65b3afa6e192e6a601cdbe5cb5897'
    var rotate_left = function (n, s) {
        var t4 = (n << s) | (n >>> (32 - s));
        return t4;
    };

/*var lsb_hex = function (val) { // Not in use; needed?
        var str="";
        var i;
        var vh;
        var vl;

        for ( i=0; i<=6; i+=2 ) {
            vh = (val>>>(i*4+4))&0x0f;
            vl = (val>>>(i*4))&0x0f;
            str += vh.toString(16) + vl.toString(16);
        }
        return str;
    };*/

    var cvt_bin = function (val) {
        var str = "";
        var i;
        var v;

        for (i = 3; i >= 0; i--) {
            v = (val >>> (i * 8)) & 0xff;
            str += String.fromCharCode(v);
        }
        return str;
    };

    var cvt_hex = function (val) {
        var str = "";
        var i;
        var v;

        for (i = 7; i >= 0; i--) {
            v = (val >>> (i * 4)) & 0x0f;
            str += v.toString(16);
        }
        return str;
    };

    var blockstart;
    var i, j;
    var W = new Array(80);
    var H0 = 0x67452301;
    var H1 = 0xEFCDAB89;
    var H2 = 0x98BADCFE;
    var H3 = 0x10325476;
    var H4 = 0xC3D2E1F0;
    var A, B, C, D, E;
    var temp;

    //str = this.utf8_encode(str);
    str = String(str);
    var str_len = str.length;

    var word_array = [];
    for (i = 0; i < str_len - 3; i += 4) {
        j = str.charCodeAt(i) << 24 | str.charCodeAt(i + 1) << 16 | str.charCodeAt(i + 2) << 8 | str.charCodeAt(i + 3);
        word_array.push(j);
    }

    switch (str_len % 4) {
    case 0:
        i = 0x080000000;
        break;
    case 1:
        i = str.charCodeAt(str_len - 1) << 24 | 0x0800000;
        break;
    case 2:
        i = str.charCodeAt(str_len - 2) << 24 | str.charCodeAt(str_len - 1) << 16 | 0x08000;
        break;
    case 3:
        i = str.charCodeAt(str_len - 3) << 24 | str.charCodeAt(str_len - 2) << 16 | str.charCodeAt(str_len - 1) << 8 | 0x80;
        break;
    }

    word_array.push(i);

    while ((word_array.length % 16) != 14) {
        word_array.push(0);
    }

    word_array.push(str_len >>> 29);
    word_array.push((str_len << 3) & 0x0ffffffff);

    for (blockstart = 0; blockstart < word_array.length; blockstart += 16) {
        for (i = 0; i < 16; i++) {
            W[i] = word_array[blockstart + i];
        }
        for (i = 16; i <= 79; i++) {
            W[i] = rotate_left(W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16], 1);
        }


        A = H0;
        B = H1;
        C = H2;
        D = H3;
        E = H4;

        for (i = 0; i <= 19; i++) {
            temp = (rotate_left(A, 5) + ((B & C) | (~B & D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 20; i <= 39; i++) {
            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 40; i <= 59; i++) {
            temp = (rotate_left(A, 5) + ((B & C) | (B & D) | (C & D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 60; i <= 79; i++) {
            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        H0 = (H0 + A) & 0x0ffffffff;
        H1 = (H1 + B) & 0x0ffffffff;
        H2 = (H2 + C) & 0x0ffffffff;
        H3 = (H3 + D) & 0x0ffffffff;
        H4 = (H4 + E) & 0x0ffffffff;
    }

    if (binary === true) 
      return cvt_bin(H0) + cvt_bin(H1) + cvt_bin(H2) + cvt_bin(H3) + cvt_bin(H4);
    temp = cvt_hex(H0) + cvt_hex(H1) + cvt_hex(H2) + cvt_hex(H3) + cvt_hex(H4);
    return temp.toLowerCase();
}

function base64_encode (data) {
    // http://kevin.vanzonneveld.net
    // +   original by: Tyler Akins (http://rumkin.com)
    // +   improved by: Bayron Guevara
    // +   improved by: Thunder.m
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Pellentesque Malesuada
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Rafał Kukawski (http://kukawski.pl)
    // *     example 1: base64_encode('Kevin van Zonneveld');
    // *     returns 1: 'S2V2aW4gdmFuIFpvbm5ldmVsZA=='
    // mozilla has this native
    // - but breaks in 2.0.0.12!
    //if (typeof this.window['atob'] == 'function') {
    //    return atob(data);
    //}
    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
        ac = 0,
        enc = "",
        tmp_arr = [];

    if (!data) {
        return data;
    }

    do { // pack three octets into four hexets
        o1 = data.charCodeAt(i++);
        o2 = data.charCodeAt(i++);
        o3 = data.charCodeAt(i++);

        bits = o1 << 16 | o2 << 8 | o3;

        h1 = bits >> 18 & 0x3f;
        h2 = bits >> 12 & 0x3f;
        h3 = bits >> 6 & 0x3f;
        h4 = bits & 0x3f;

        // use hexets to index into b64, and append result to encoded string
        tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
    } while (i < data.length);

    enc = tmp_arr.join('');
    
    var r = data.length % 3;
    
    return (r ? enc.slice(0, r - 3) : enc) + '==='.slice(r || 3);

}

function tocs2mbtoc(toc_s)
{
  var ids = toc_s.split(':');
  var trackcount = ids.length - 1;
  var lastaudio = trackcount;
  while (lastaudio > 0 && ids[lastaudio - 1][0] == '-')
    lastaudio --;
  for (var i = 0; i < ids.length; i++)
    ids[i] = Math.abs(Number(ids[i]));
  var mbtoc = '1 ' + lastaudio;
  if (lastaudio == trackcount) // Audio CD
    mbtoc += ' ' + (ids[lastaudio] + 150);
  else // Enhanced CD
    mbtoc += ' ' + (ids[lastaudio] + 150 - 11400);
  for (var tr = 0; tr < lastaudio; tr++)
    mbtoc += ' ' + (ids[tr] + 150);
  return mbtoc;
}

function tocs2mbid(toc_s)
{
  var ids = toc_s.split(':');
  var trackcount = ids.length - 1;
  var lastaudio = trackcount;
  while (lastaudio > 0 && ids[lastaudio - 1][0] == '-')
    lastaudio --;
  for (var i = 0; i < ids.length; i++)
    ids[i] = Math.abs(Number(ids[i]));
  var mbtoc = '01' + str_pad_num(lastaudio.toString(16).toUpperCase(),2);
  if (lastaudio == trackcount) // Audio CD
    mbtoc += decimalToHexString(ids[lastaudio] + 150);
  else // Enhanced CD
    mbtoc += decimalToHexString(ids[lastaudio] + 150 - 11400);
  for (tr = 0; tr < lastaudio; tr++)
    mbtoc += decimalToHexString(ids[tr] + 150);
  mbtoc = str_pad(mbtoc,804,'0');
  return base64_encode(sha1(mbtoc,true)).replace(/\+/g, '.').replace(/\//g, '_').replace(/=/g, '-');
}

function tocs2cddbid(toc_s)
{
  var ids = toc_s.split(':');
  var trackcount = ids.length - 1;
  var tocid = '';
  for (var tr = 0; tr < trackcount; tr++) {
    ids[tr] = Math.abs(Number(ids[tr]));
    tocid += String(Math.floor(ids[tr] / 75) + 2);
  }
  var id0 = 0;
  for (var i = 0; i < tocid.length; i++)
    id0 += tocid.charCodeAt(i) - '0'.charCodeAt(0);
  return str_pad_num((id0 % 255).toString(16).toUpperCase(),2) +
    str_pad_num((Math.floor(ids[trackcount] / 75) - Math.floor(ids[0] / 75)).toString(16).toUpperCase(),4) +
    str_pad_num(trackcount.toString(16).toUpperCase(),2);
}

function tocs2arid(toc_s)
{
  var ids = toc_s.split(':');
  var trackcount = ids.length - 1;
  var discId1 = 0;
  var discId2 = 0;
  var n = 0;
  for (var tr = 0; tr < trackcount; tr++)
    if (ids[tr][0] != '-')
    {
      var offs = Number(ids[tr]);
      discId1 += offs;
      discId2 += Math.max(1,offs) * (++n);
    }
  var leadout = Math.abs(Number(ids[tr]));
  discId1 += leadout;
  discId2 += Math.max(1,leadout) * (++n);
  return (decimalToHexString(discId1) + '-' + decimalToHexString(discId2) + '-' + tocs2cddbid(toc_s)).toLowerCase();
}


/* COINWIDGET */
/**
 *
 * Donations welcome:
 * 	BTC: 122MeuyZpYz4GSHNrF98e6dnQCXZfHJeGS
 * 		LTC: LY1L6M6yG26b4sRkLv4BbkmHhPn8GR5fFm
 * 				~ Thank you!
 *
 * 				------------
 *
 * 				MIT License (MIT)
 *
 * 				Copyright (c) 2013 http://coinwidget.com/ 
 * 				Copyright (c) 2013 http://scotty.cc/
 *
 * 				Permission is hereby granted, free of charge, to any person obtaining a copy
 * 				of this software and associated documentation files (the "Software"), to deal
 * 				in the Software without restriction, including without limitation the rights
 * 				to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * 				copies of the Software, and to permit persons to whom the Software is
 * 				furnished to do so, subject to the following conditions:
 *
 * 				The above copyright notice and this permission notice shall be included in
 * 				all copies or substantial portions of the Software.
 *
 * 				THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * 				IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * 				FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * 				AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * 				LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * 				OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * 				THE SOFTWARE.
 *
 * 				*/

if (typeof CoinWidgetComCounter != 'number')
var CoinWidgetComCounter = 0;

if (typeof CoinWidgetCom != 'object')
var CoinWidgetCom = {
	cdnsource: 'http://s3.cuetools.net/cw/'
	, source: '/cw/'
	, config: []
	, go :function(config) {
		config = CoinWidgetCom.validate(config);
		CoinWidgetCom.config[CoinWidgetComCounter] = config;
                CoinWidgetCom.init();
		document.write('<span data-coinwidget-instance="'+CoinWidgetComCounter+'" class="COINWIDGETCOM_CONTAINER"></span>');
		CoinWidgetComCounter++;
	}
	, validate: function(config) {
		var $accepted = [];
		$accepted['currencies'] = ['bitcoin','litecoin'];
		$accepted['counters'] = ['count','amount','hide'];
		$accepted['alignment'] = ['al','ac','ar','bl','bc','br'];
		if (!config.currency || !CoinWidgetCom.in_array(config.currency,$accepted['currencies']))
			config.currency = 'bitcoin';
		if (!config.counter || !CoinWidgetCom.in_array(config.counter,$accepted['counters']))
			config.counter = 'count';
		if (!config.alignment || !CoinWidgetCom.in_array(config.alignment,$accepted['alignment']))
			config.alignment = 'bl';
		if (typeof config.qrcode != 'boolean')
			config.qrcode = true;
		if (typeof config.auto_show != 'boolean')
			config.auto_show = false;
		if (!config.wallet_address)
			config.wallet_address = 'My '+ config.currency +' wallet_address is missing!';
		if (!config.lbl_button) 
			config.lbl_button = 'Donate';
		if (!config.lbl_address)
			config.lbl_address = 'My Bitcoin Address:';
		if (!config.lbl_count)
			config.lbl_count = 'Donation';
		if (!config.lbl_amount)
			config.lbl_amount = 'BTC';
		if (typeof config.decimals != 'number' || config.decimals < 0 || config.decimals > 10)
			config.decimals = 4;

		return config;
	}
	, init: function(){
		$(window).resize(function(){
			CoinWidgetCom.window_resize();
		});
		setTimeout(function(){
			/* this delayed start gives the page enough time to 
 * 			   render multiple widgets before pinging for counts.
 * 			   			*/
			CoinWidgetCom.build();
		},800);		
	}
	, build: function(){
		$containers = $("span[data-coinwidget-instance]");
		$containers.each(function(i,v){
			$config = CoinWidgetCom.config[$(this).attr('data-coinwidget-instance')];
			$counter = $config.counter == 'hide'?'':('<span><img src="'+CoinWidgetCom.cdnsource+'icon_loading.gif" width="13" height="13" /></span>');
			$button = '<a class="COINWIDGETCOM_BUTTON_'+$config.currency.toUpperCase()+'" href="#"><img src="'+CoinWidgetCom.cdnsource+'icon_'+$config.currency+'.png" /><span>'+$config.lbl_button+'</span></a>'+$counter;
			$(this).html($button);
			$(this).find('> a').unbind('click').click(function(e){
				e.preventDefault();
				CoinWidgetCom.show(this);
			});
		});
		CoinWidgetCom.counters();
	}
	, window_resize: function(){
		$.each(CoinWidgetCom.config,function(i,v){
			CoinWidgetCom.window_position(i);
		});
	}
	, window_position: function($instance){
		$config = CoinWidgetCom.config[$instance];
		coin_window = "#COINWIDGETCOM_WINDOW_"+$instance;

			obj = "span[data-coinwidget-instance='"+$instance+"'] > a";
			/* 	to make alignment relative to the full width of the container instead 
 *  				of just the button change this occurence of $(obj) to $(obj).parent(), 
 *  							do the same for the occurences within the switch statement. */
			$pos = $(obj).offset(); 
			switch ($config.alignment) {
				default:
				case 'al': /* above left */
					$top = $pos.top - $(coin_window).outerHeight() - 10;
					$left = $pos.left; 
					break;
				case 'ac': /* above center */
					$top = $pos.top - $(coin_window).outerHeight() - 10;
					$left = $pos.left + ($(obj).outerWidth()/2) - ($(coin_window).outerWidth()/2);
					break;
				case 'ar': /* above right */
					$top = $pos.top - $(coin_window).outerHeight() - 10;
					$left = $pos.left + $(obj).outerWidth() - $(coin_window).outerWidth();
					break;
				case 'bl': /* bottom left */
					$top = $pos.top + $(obj).outerHeight() + 10;
					$left = $pos.left; 
					break;
				case 'bc': /* bottom center */
					$top = $pos.top + $(obj).outerHeight() + 10;
					$left = $pos.left + ($(obj).outerWidth()/2) - ($(coin_window).outerWidth()/2);
					break;
				case 'br': /* bottom right */
					$top = $pos.top + $(obj).outerHeight() + 10;
					$left = $pos.left + $(obj).outerWidth() - $(coin_window).outerWidth();
					break;
			}
		if ($(coin_window).is(':visible')) {
			$(coin_window).stop().animate({'z-index':99999999999,'top':$top,'left':$left},150);
		} else {
			$(coin_window).stop().css({'z-index':99999999998,'top':$top,'left':$left});
		}
	}
	, counter: []
	, counters: function(){
		$addresses = [];
		$.each(CoinWidgetCom.config,function(i,v){
			$instance = i;
			$config = v;
			if ($config.counter != 'hide')
				$addresses.push($instance+'_'+$config.currency+'_'+$config.wallet_address);
			else {
				if ($config.auto_show) 
					$("span[data-coinwidget-instance='"+i+"']").find('> a').click();
			}
		});
		if ($addresses.length) {
			CoinWidgetCom.loader.script({
				id: 'COINWIDGETCOM_INFO'+Math.random()
				, source: (CoinWidgetCom.source+'lookup.php?data='+$addresses.join('|'))
				, callback: function(){
					if (typeof COINWIDGETCOM_DATA == 'object') {
						CoinWidgetCom.counter = COINWIDGETCOM_DATA;
						$.each(CoinWidgetCom.counter,function(i,v){
							$config = CoinWidgetCom.config[i];
							if (!v.count || v == null) v = {count:0,amount:0};
							$("span[data-coinwidget-instance='"+i+"']").find('> span').html($config.counter=='count'?v.count:(v.amount.toFixed($config.decimals)+' '+$config.lbl_amount));
							if ($config.auto_show) {
								$("span[data-coinwidget-instance='"+i+"']").find('> a').click();
							}
						});
					}
					if ($("span[data-coinwidget-instance] > span img").length > 0) {
						setTimeout(function(){CoinWidgetCom.counters();},2500);
					}
				}
			});
		}
	}
	, show: function(obj) {
		$instance = $(obj).parent().attr('data-coinwidget-instance');
		$config = CoinWidgetCom.config[$instance];
		coin_window = "#COINWIDGETCOM_WINDOW_"+$instance;
		$(".COINWIDGETCOM_WINDOW").css({'z-index':99999999998});
		if (!$(coin_window).length) {

			$sel = !navigator.userAgent.match(/iPhone/i)?'onclick="this.select();"':'onclick="prompt(\'Select all and copy:\',\''+$config.wallet_address+'\');"';

			$html = ''
				  + '<label>'+$config.lbl_address+'</label>'
				  + '<input type="text" readonly '+$sel+'  value="'+$config.wallet_address+'" />'
				  + '<a class="COINWIDGETCOM_CREDITS" href="http://coinwidget.com/" target="_blank">CoinWidget.com</a>'
  				  + '<a class="COINWIDGETCOM_WALLETURI" href="'+$config.currency.toLowerCase()+':'+$config.wallet_address+'" target="_blank" title="Click here to send this address to your wallet (if your wallet is not compatible you will get an empty page, close the white screen and copy the address by hand)" ><img src="'+CoinWidgetCom.cdnsource+'icon_wallet.png" /></a>'
  				  + '<a class="COINWIDGETCOM_CLOSER" href="javascript:;" onclick="CoinWidgetCom.hide('+$instance+');" title="Close this window">x</a>'
  				  + '<img class="COINWIDGET_INPUT_ICON" src="'+CoinWidgetCom.cdnsource+'icon_'+$config.currency+'.png" width="16" height="16" title="This is a '+$config.currency+' wallet address." />'
				  ;
			if ($config.counter != 'hide') {
				$html += '<span class="COINWIDGETCOM_COUNT">0<small>'+$config.lbl_count+'</small></span>'
				  	  + '<span class="COINWIDGETCOM_AMOUNT end">0.00<small>'+$config.lbl_amount+'</small></span>'
				  	  ;				  
			}
			if ($config.qrcode) {
				$html += '<img class="COINWIDGETCOM_QRCODE" data-coinwidget-instance="'+$instance+'" src="'+CoinWidgetCom.cdnsource+'icon_qrcode.png" width="16" height="16" />'
				  	   + '<img class="COINWIDGETCOM_QRCODE_LARGE" src="'+CoinWidgetCom.cdnsource+'icon_qrcode.png" width="111" height="111" />'
				  	   ;
			}
			var $div = $('<div></div>');
			$('body').append($div);
			$div.attr({
				'id': 'COINWIDGETCOM_WINDOW_'+$instance
			}).addClass('COINWIDGETCOM_WINDOW COINWIDGETCOM_WINDOW_'+$config.currency.toUpperCase()+' COINWIDGETCOM_WINDOW_'+$config.alignment.toUpperCase()).html($html).unbind('click').bind('click',function(){
				$(".COINWIDGETCOM_WINDOW").css({'z-index':99999999998});
				$(this).css({'z-index':99999999999});
			});
			if ($config.qrcode) {
				$(coin_window).find('.COINWIDGETCOM_QRCODE').bind('mouseenter click',function(){
					$config = CoinWidgetCom.config[$(this).attr('data-coinwidget-instance')];
					$lrg = $(this).parent().find('.COINWIDGETCOM_QRCODE_LARGE');
					if ($lrg.is(':visible')) {
						$lrg.hide();
						return;
					}
					$lrg.attr({
						src: CoinWidgetCom.source +'qr/?address='+$config.wallet_address
					}).show();
				}).bind('mouseleave',function(){
					$lrg = $(this).parent().find('.COINWIDGETCOM_QRCODE_LARGE');
					$lrg.hide();
				});
			}
		} else {
			if ($(coin_window).is(':visible')) {
				CoinWidgetCom.hide($instance);
				return;
			}
		}
		CoinWidgetCom.window_position($instance);
		$(coin_window).show();
		$pos = $(coin_window).find('input').position();
		$(coin_window).find('img.COINWIDGET_INPUT_ICON').css({'top':$pos.top+3,'left':$pos.left+3});
		$(coin_window).find('.COINWIDGETCOM_WALLETURI').css({'top':$pos.top+3,'left':$pos.left+$(coin_window).find('input').outerWidth()+3});
		if ($config.counter != 'hide') {
			$counters = CoinWidgetCom.counter[$instance];
			if ($counters == null) {
				$counters = {
					count: 0,
					amount: 0
				};
			}
		 	if ($counters.count == null) $counters.count = 0;
		 	if ($counters.amount == null) $counters.amount = 0;
			$(coin_window).find('.COINWIDGETCOM_COUNT').html($counters.count+ '<small>'+$config.lbl_count+'</small>');
			$(coin_window).find('.COINWIDGETCOM_AMOUNT').html($counters.amount.toFixed($config.decimals)+ '<small>'+$config.lbl_amount+'</small>');
		}
		if (typeof $config.onShow == 'function') 
			$config.onShow();
	}
	, hide: function($instance) {
		$config = CoinWidgetCom.config[$instance];
		coin_window = "#COINWIDGETCOM_WINDOW_"+$instance;
		$(coin_window).fadeOut();
		if (typeof $config.onHide == 'function') {
			$config.onHide();
		}
	}
	, in_array: function(needle,haystack) {
		for (i=0;i<haystack.length;i++) {
			if (haystack[i] == needle) { 
				return true;
			}
		}
		return false;
	}
	, loader: {
		script: function(obj){
			if (!document.getElementById(obj.id)) {
				var x = document.createElement('script');
				x.onreadystatechange = function(){
					switch (this.readyState) {
						case 'complete':
						case 'loaded':
							obj.callback();
							break;
					}
				};
				x.onload = function(){
					obj.callback();
				};
				x.src = obj.source;
				x.id  = obj.id;
				document.lastChild.firstChild.appendChild(x);
			}
		}
	}
};

