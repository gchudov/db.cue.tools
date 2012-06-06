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
    mbdata.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    mbdata.setProperty(row, 6, 'className', 'google-visualization-table-td google-visualization-table-td-ctdb');
    var title = $('<div/>').text(mbdata.getValue(row, 2)).html();
    if (mbdata.getValue(row, 9) == 'musicbrainz')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-musicbrainz"><a target=_blank href="http://musicbrainz.org/release/' + mbdata.getValue(row, 8) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 9) == 'discogs')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-discogs"><a target=_blank href="http://www.discogs.com/release/' + mbdata.getValue(row, 8) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 9) == 'freedb')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-freedb"><a target=_blank href="http://www.freedb.org/freedb/' + mbdata.getValue(row, 8) + '">' + title + '</a></div>');
    if (mbdata.getValue(row, 4) != null) {
    var flags = new Array('us','gb','xe');
    var flagno = flags.indexOf(mbdata.getValue(row, 4).toLowerCase());
    if (flagno < 0)
    mbdata.setFormattedValue(row, 4, '<div style="padding: 0; width: 16px; height: 11px; background: url(&quot;http://s3.cuetools.net/flags/' + mbdata.getValue(row, 4).toLowerCase() + '.png&quot;) no-repeat scroll 0 0 transparent">');
    else
    mbdata.setFormattedValue(row, 4, '<div style="padding: 0; width: 16px; height: 11px; background: url(&quot;http://s3.cuetools.net/flags/flags.png?id=2&quot;) no-repeat scroll 0 -' + flagno * 11 + 'px transparent">');
    }
    mbdata.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    mbdata.setFormattedValue(row, 6, mbdata.getValue(row, 6).substring(0, 30));
    mbdata.setProperty(row, 7, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    mbdata.setProperty(row, 10, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    if (mbdata.getValue(row,10) != null) {
      var diff = 100 - mbdata.getValue(row,10);
      color = (255 - diff).toString(16).toUpperCase() + (255 - Math.floor(diff*0.7)).toString(16).toUpperCase() + "FF";
      mbdata.setProperty(row, 10, 'style', 'background-color:#' + color + ';');
    //mbdata.setFormattedValue(row, 10, '<span style="background-color:#' + color + ';">' + mbdata.getValue(row,10) + '</span>');
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
