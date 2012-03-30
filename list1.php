<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript' src="<?php echo $ctdbcfg_s3?>/ctdb.js?id=<?php echo $ctdbcfg_s3_id?>"></script>
<script type="text/javascript" src="<?php echo $ctdbcfg_s3?>/shadowbox-3.0.3/shadowbox.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $ctdbcfg_s3?>/shadowbox-3.0.3/shadowbox.css">
<script type='text/javascript'>
google.setOnLoadCallback(drawTable);
function drawTable() 
{
  var data = ctdbEntryData(<?php echo $json_entries?>);
  var table = new google.visualization.Table(document.getElementById('entries_div'));
  var opts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, pageSize: <?php echo $count?>, page: 'event', pagingButtonsConfiguration : 'both'};
  var start = <?php echo $start?>;
  var prev = start != 0;
  var next = data.getNumberOfRows() >= <?php echo $count?>;
  var mbdiv = document.getElementById('musicbrainz_div');
  var mbtable = mbdiv == null ? null : new google.visualization.Table(mbdiv);
  var sbdiv = document.getElementById('submissions_div');
  var sbtable = sbdiv == null ? null :  new google.visualization.Table(sbdiv);
  var trdiv = document.getElementById('tracks_div');
  var trtable = trdiv == null ? null : new google.visualization.Table(trdiv);
  var trdata = new google.visualization.DataTable();
  var mbdata = new google.visualization.DataTable();

  Shadowbox.init();

  trdata.addColumn('string', 'Track');
  trdata.addColumn('string', 'Start');
  trdata.addColumn('string', 'Length');
  trdata.addColumn('number', 'Start');
  trdata.addColumn('number', 'End');
  trdata.addColumn('string', 'CRC');

  opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
  google.visualization.events.addListener(table, 'page', function(e) {
    var xmlhttp = new XMLHttpRequest();
    var shift = <?php echo $count?> * e['page'];
    xmlhttp.open("GET", '?json=1&start=' + (start + shift) + '<?php echo $url?>', false); // true
    xmlhttp.send(null);
    if (xmlhttp.readyState != 4) {
      alert('error ' + xmlhttp.readyState);
      return;
    }
    if (xmlhttp.status != 200) {
      alert(xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText);
      return;
    }
    data = ctdbEntryData(xmlhttp.responseText);
    start += shift;
    prev = start != 0;
    next = data.getNumberOfRows() >= <?php echo $count?>;
    opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
    var view = new google.visualization.DataView(data);
    view.hideColumns([6,7,8]);
    table.draw(view, opts);
    if (mbdiv != null) mbdiv.innerHTML = '';
    if (sbdiv != null) sbdiv.innerHTML = '';
  });
  var coverartElement = document.getElementById('coverart');
  var videosElement = document.getElementById('videos');
  var ctdbbox_div = document.getElementById('ctdbbox_div');
  function resetCoverart() {
    if (ctdbbox_div == null) return;
    if (table.getSelection().length == 0) {
      ctdbbox_div.style.display = 'none';
      return;
    }

    var srow = table.getSelection()[0].row;
    var cnf = Number(data.getValue(srow, 5));
    var crc = Number(data.getValue(srow, 6));
    var toc_s = data.getValue(srow, 7);
    var toc = toc_s.split(':');
    var crcs_s = data.getValue(srow, 8);
    var crcs = crcs_s != null ? crcs_s.split(' ') : new Array();
    var ntracks = toc.length - 1;
    ctdbbox_div.style.display = 'inherit';
    document.getElementById('ctdbbox_div_mbi').innerHTML = tocs2mbid(toc_s);
    document.getElementById('ctdbbox_div_mbi').attributes.href.value = 'http://musicbrainz.org/bare/cdlookup.html?toc=' + tocs2mbtoc(toc_s);
    document.getElementById('ctdbbox_div_crc').innerHTML = decimalToHexString(crc);
    document.getElementById('ctdbbox_div_cnf').innerHTML = cnf;
    document.getElementById('ctdbbox_div_tid').innerHTML = data.getValue(srow, 2);
    document.getElementById('ctdbbox_div_tid').attributes.href.value = '/lookup2.php?version=2&ctdb=1&metadata=extensive&fuzzy=1&toc=' + toc_s;
    document.getElementById('ctdbbox_div_fdb').innerHTML = tocs2cddbid(toc_s);
    document.getElementById('ctdbbox_div_ari').innerHTML = tocs2arid(toc_s);
    if (trdiv != null) {
      trdata.removeRows(0, trdata.getNumberOfRows());
      trdata.addRows(ntracks);
      var tracklist_row = mbtable.getSelection().length > 0 ? mbtable.getSelection()[0].row : 0;
      var tracklist = mbdata.getNumberOfRows() > tracklist_row ? mbdata.getValue(tracklist_row,13) : new Array();
      var artist = mbdata.getNumberOfRows() > tracklist_row ? mbdata.getValue(tracklist_row,1) : null;
      var trmod = 0;
      for(var tr=0; tr < trdata.getNumberOfRows(); tr++) {
        var trstart = 150 + Math.abs(Number(toc[tr]));
        var trend = 149 + Math.abs(Number(toc[tr + 1]));
        if (toc[tr+1][0] == '-') trend -= 11400;
        var trcrc = toc[tr][0] != '-' && trmod >= 0 && trmod < crcs.length ? crcs[trmod] : '';
        if (toc[tr][0] != '-') trmod ++;
        trdata.setValue(tr, 0, tr in tracklist ? '<span>' + tracklist[tr].name + '</span>' + (tracklist[tr].artist == null || tracklist[tr].artist == artist ? '' : '<span style="margin:0; color:#888;"> (' + tracklist[tr].artist + ')</span>')  : toc[tr][0] == '-' ? '[data track]' : '');
        trdata.setValue(tr, 1, TimeToString(trstart));
        trdata.setValue(tr, 2, TimeToString(trend + 1 - trstart));
        trdata.setValue(tr, 3, trstart);
        trdata.setValue(tr, 4, trend);
        trdata.setValue(tr, 5, trcrc);
        trdata.setProperty(tr, 0, 'className', 'google-visualization-table-td google-visualization-table-td-nowrap');
        trdata.setProperty(tr, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        trdata.setProperty(tr, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        trdata.setProperty(tr, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        trdata.setProperty(tr, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        trdata.setProperty(tr, 5, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
      }
      var tropts = {allowHtml: true, width: 800, sort: 'disable', showRowNumber: true, page: 'enable', pageSize: 13};
      trtable.draw(trdata, tropts);
    } 
    if (coverartElement != null && videosElement != null) {
      Shadowbox.teardown('a.thumbnail');
      var imglist1 = new Array();
      var vidlist1 = new Array();
      for (var row = 0; row < mbdata.getNumberOfRows(); row++) {
        var imglist2 = mbdata.getValue(row, 11);
        if (imglist2 != null) imglist1 = imglist1.concat(imglist2);
        var vidlist2 = mbdata.getValue(row, 12);
        if (vidlist2 != null) vidlist1 = vidlist1.concat(vidlist2);
      }
      var imglist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,11) : imglist1;
      coverartElement.innerHTML = ctdbCoverart(imglist, mbtable.getSelection().length == 0, 4);
      var vidlist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,12) : vidlist1;
      videosElement.innerHTML = ctdbVideos(vidlist, 3);
      Shadowbox.setup('a.thumbnail', {autoplayMovies: true});
    }
  }
  if (mbdiv != null)
    google.visualization.events.addListener(table, 'select', function() {
//      mbtable.setSelection();
      //if (mbtable.getSelection().length > 0)
      if (mbdata.getNumberOfRows() > 0) {
        mbtable.setSelection();
        mbdata.removeRows(0, mbdata.getNumberOfRows());
      }
      resetCoverart();
      if (table.getSelection().length == 0) {
        mbdiv.innerHTML = '';
        return;
      }
      var srow = table.getSelection()[0].row;
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.open("GET", '/lookup2.php?type=json&ctdb=0&metadata=default&fuzzy=1&toc=' + data.getValue(srow, 7), true);
      mbdiv.innerHTML = '<img src="http://s3.cuetools.net/throb.gif" alt="Looking up metadata...">';
      xmlhttp.onreadystatechange=function() {
        if (xmlhttp.readyState != 4 || xmlhttp.status == 0) return;
        if (xmlhttp.status != 200) {
          mbdiv.innerHTML = xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText;
          xmlhttp = null;
          return;
        }
        if (xmlhttp.responseText == 'null') {
          mbdiv.innerHTML = '<img src="http://s3.cuetools.net/face-sad.png" alt="No metadata found">';
          xmlhttp = null;
          return;
        }
        mbdata = ctdbMetaData(xmlhttp.responseText);
        xmlhttp = null;
        var mbview = new google.visualization.DataView(mbdata);
        mbview.hideColumns([8,9,11,12,13]); 
        mbtable.draw(mbview, {allowHtml: true, width: 1200, page: 'enable', pageSize: 5, sort: 'disable', showRowNumber: false});
        resetCoverart();
      }
      xmlhttp.send(null);
    });
  if (mbdiv != null)
    google.visualization.events.addListener(mbtable, 'select', function() {
      resetCoverart();
    /*  var admdiv = document.getElementById('admin_div');
      admdiv.innerHTML = '';
      if (mbtable.getSelection().length == 0)
        return;
      var srow = mbtable.getSelection()[0].row;
      //var set = new Image();
      //set.src = "http://s3.cuetools.net/face-sad.png";
      //set.id = "set_metadata";
      //admdiv.appendChild(set);
      //admdiv.setAttribute("onClick", "setMetadata()");
      admargs = { "entry" : table.getSelection(), "release" : mbtable.getSelection() };
      admdiv.innerHTML = '<a onClick="setMetadata(&quot;' + encodeURIComponent(JSON.stringify(admargs)) +  '&quot;)">set</a>';*/
    });

  if (sbdiv != null)
     google.visualization.events.addListener(table, 'select', function() {
      if (table.getSelection().length == 0) {
        sbdiv.innerHTML = '';
        return;
      }
      var srow = table.getSelection()[0].row;
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.open("GET", '/recent.php?json=1&tocid=' + data.getValue(srow, 2), true);
      sbdiv.innerHTML = '<img src="http://s3.cuetools.net/throb.gif" alt="Loading submissions log...">';
      xmlhttp.onreadystatechange=function() {
        if (xmlhttp.readyState != 4 || xmlhttp.status == 0) return;
        if (xmlhttp.status != 200) {
          sbdiv.innerHTML = xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText;
          xmlhttp = null;
          return;
        }
        if (xmlhttp.responseText == 'null') {
          sbdiv.innerHTML = '<img src="http://s3.cuetools.net/face-sad.png" alt="No submissions found">';
          xmlhttp = null;
          return;
        }
        var sbdata = ctdbSubmissionData(xmlhttp.responseText);
        xmlhttp = null;
        var sbopts = {allowHtml: true, width: 800, sort: 'disable', showRowNumber: false, page: 'enable', pageSize: 5};
        var sbview = new google.visualization.DataView(sbdata);
        sbview.hideColumns([5,6,7,8,11,12]);
        sbtable.draw(sbview, sbopts);
      }
      xmlhttp.send(null);
    });
  var view = new google.visualization.DataView(data);
  view.hideColumns([6,7,8]);
  table.draw(view, opts);
};
function setMetadata(strargs) {
  var admdiv = document.getElementById('admin_div');
  var args = JSON.parse(decodeURIComponent(strargs));
  admdiv.innerHTML = JSON.stringify(args);
};
</script>
