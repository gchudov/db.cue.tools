function decimalToHexString(number)
{
  var hex = (number < 0 ?  0xFFFFFFFF + number + 1 : number).toString(16).toUpperCase();
  return "00000000".substr(0, 8 - hex.length) + hex;
};

function pad2(n)
{
  return (n < 10 ? '0' : '') + n;
}

function ctdbVideos(vidlist)
{
  var html = '';
  var vidfound = new Object;
  var vidfoundlen = 0;
  for (var ivid in vidlist) {
    var vid = vidlist[ivid].uri;
    if (vid in vidfound) continue;
    vidfound[vid] = 1;
    vidfoundlen ++;
    if (vidfoundlen > 1)
      html += '<span style="display:none;">';
    var yid = vid.substr(31);
    html += '<a class="thumbnail" title="' + vidlist[ivid].title + '" href="http://www.youtube.com/v/' + yid + '&hl=en&fs=1&rel=0&autoplay=1" rel="shadowbox[vids];height=480;width=700;player=swf">';
    if (vidfoundlen > 1)
      html += ' </a></span>';
    else
      html += '<img src="http://i.ytimg.com/vi/' + yid + '/default.jpg"></a>';
  }
  return html;
}

function ctdbCoverart(imglist,primary)
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
    if (imgfoundlen > 1)
      html += '<span style="display:none;">';
    var sz = '';
    if (img == imglist[iimg].uri) {
      if ('height' in imglist[iimg]) sz += ";height=" + imglist[iimg].height;
      if ('width' in imglist[iimg]) sz += ";width=" + imglist[iimg].width;
    }
    html += '<a class="thumbnail" href="' + img + '" rel="shadowbox[covers];player=img' + sz + '">';
    if (imgfoundlen > 1)
      html += ' </a></span>';
    else
      html += '<img src="' + imglist[iimg].uri150 + '"></a>';
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
    data.setFormattedValue(row, 4, '<a href="show.php?id=' + data.getValue(row, 4).toString(10) + '">' + decimalToHexString(data.getValue(row, 6)) + '</a>');
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
    if (mbdata.getValue(row, 9) == 'musicbrainz')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-musicbrainz"><a target=_blank href="http://musicbrainz.org/release/' + mbdata.getValue(row, 8) + '">' + mbdata.getValue(row, 2) + '</a></div>');
    if (mbdata.getValue(row, 9) == 'discogs')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-discogs"><a target=_blank href="http://www.discogs.com/release/' + mbdata.getValue(row, 8) + '">' + mbdata.getValue(row, 2) + '</a></div>');
    if (mbdata.getValue(row, 9) == 'freedb')
      mbdata.setFormattedValue(row, 2, '<div class="ctdb-meta-freedb"><a target=_blank href="http://www.freedb.org/freedb/' + mbdata.getValue(row, 8) + '">' + mbdata.getValue(row, 2) + '</a></div>');
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
    data.setFormattedValue(row, 9, '<a href="show.php?id=' + data.getValue(row, 9).toString(10) + '">' + decimalToHexString(data.getValue(row, 11)) + '</a>');
    data.setProperty(row, 10, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 13, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 14, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
  }
  return data;
};
