<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
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
  var mbdiv = $('#musicbrainz_div');
  var mbtable = mbdiv.length == 0 ? null : new google.visualization.Table(mbdiv[0]);
  var sbdiv = document.getElementById('submissions_div');
  var sbtable = sbdiv == null ? null :  new google.visualization.Table(sbdiv);
  var trdiv = document.getElementById('tracks_div');
  var trtable = trdiv == null ? null : new google.visualization.Table(trdiv);
  var trdata = new google.visualization.DataTable();
  var mbdata = new google.visualization.DataTable();

//  mbdiv.each(function() { $(this).data('gvtable', new google.visualization.Table($(this)[0])); });

  Shadowbox.init();

  trdata.addColumn('string', 'Track');
  trdata.addColumn('string', 'Start');
  trdata.addColumn('string', 'Length');
  trdata.addColumn('number', 'Start');
  trdata.addColumn('number', 'End');
  trdata.addColumn('string', 'CRC');

  opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
  var ctdbbox_div = $('#ctdbbox_div');

  function resetCoverart() {
    if (table.getSelection().length == 0) {
      ctdbbox_div.hide();
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
    var tracklist_row = mbtable.getSelection().length > 0 ? mbtable.getSelection()[0].row : 0;
    var artist = mbdata.getNumberOfRows() > tracklist_row ? mbdata.getValue(tracklist_row,1) : data.getValue(srow, 0);
    var title = mbdata.getNumberOfRows() > tracklist_row ? mbdata.getValue(tracklist_row,2) : data.getValue(srow, 1);

    $('#ctdbbox_div_mbi').text(tocs2mbid(toc_s));
    $('#ctdbbox_div_mbi').attr('href', 'http://musicbrainz.org/bare/cdlookup.html?toc=' + tocs2mbtoc(toc_s));
    $('#ctdbbox_div_crc').text(decimalToHexString(crc));
    $('#ctdbbox_div_cnf').text(cnf);
    $('#ctdbbox_div_tid').text(data.getValue(srow, 2));
    $('#ctdbbox_div_tid').attr('href', '/lookup2.php?version=3&ctdb=1&metadata=extensive&fuzzy=1&toc=' + toc_s);
    $('#ctdbbox_div_fdb').text(tocs2cddbid(toc_s));
    $('#ctdbbox_div_ari').text(tocs2arid(toc_s));
    <?php if (isset($where_id)) { ?>
    $('#ctdbtitle').text(artist != null && title != null ? artist + ' - ' + title : '');
    <?php } ?>
    if (trdiv != null) {
      trdata.removeRows(0, trdata.getNumberOfRows());
      trdata.addRows(ntracks);
      var tracklist = mbdata.getNumberOfRows() > tracklist_row ? mbdata.getValue(tracklist_row,12) : new Array();
      var trmod = 0;
      for(var tr=0; tr < trdata.getNumberOfRows(); tr++) {
        var trstart = 150 + Math.abs(Number(toc[tr]));
        var trend = 149 + Math.abs(Number(toc[tr + 1]));
        if (toc[tr+1][0] == '-') trend -= 11400;
        var trcrc = toc[tr][0] != '-' && trmod >= 0 && trmod < crcs.length ? crcs[trmod] : '';
        if (toc[tr][0] != '-') trmod ++;
        trdata.setValue(tr, 0, tr in tracklist ? (toc[tr][0] == '-' ? '<span style="color:#A88;">' : '<span>') + tracklist[tr].name + '</span>' + (tracklist[tr].artist == null || tracklist[tr].artist == artist ? '' : '<span style="margin:0; color:#888;"> (' + tracklist[tr].artist + ')</span>')  : toc[tr][0] == '-' ? '<span style="color:#A88;">[data track]</span>' : '');
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
    Shadowbox.teardown('a.thumbnail');
    var imglist1 = new Array();
    var vidlist1 = new Array();
    for (var row = 0; row < mbdata.getNumberOfRows(); row++) {
      var imglist2 = mbdata.getValue(row, 10);
      if (imglist2 != null) imglist1 = imglist1.concat(imglist2);
      var vidlist2 = mbdata.getValue(row, 11);
      if (vidlist2 != null) vidlist1 = vidlist1.concat(vidlist2);
    }
    var imglist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,10) : imglist1;
    $("#coverart").html(ctdbCoverart(imglist, mbtable.getSelection().length == 0, 4));
    var vidlist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,11) : vidlist1;
    $("#videos").html(ctdbVideos(vidlist, 3));
    Shadowbox.setup('a.thumbnail', {autoplayMovies: true});
    ctdbbox_div.show();
  }

  function resetMetadata() {
    if (mbdata.getNumberOfRows() > 0) {
      mbtable.setSelection();
      mbdata.removeRows(0, mbdata.getNumberOfRows());
    }
    resetCoverart();
    if (table.getSelection().length == 0) {
      mbdiv.hide();
      return;
    }
    var srow = table.getSelection()[0].row;
    var toc_s = data.getValue(srow, 7);
    var toc_id = data.getValue(srow, 4);
    mbdiv.html('<center><img src="http://s3.cuetools.net/throb.gif" alt="Looking up metadata..."></center>');
    mbdiv.show();
    $.ajax({
      url: "/lookup2.php?ctdb=0&metadata=<?php echo @$_GET['metadata']=='extensive' ? 'extensive' : 'default'; ?>&fuzzy=1&jsonp=?",
      cache: true,
      data: {toc : toc_s},
      dataType: "jsonp",
      jsonpCallback: "ctdbajax" + toc_id,
      error: function() {
        mbdiv.html('<center><img src="http://s3.cuetools.net/face-sad.png" alt="No metadata found"></center>');
      },
      success: function(json) {
        if (table.getSelection().length == 0 || toc_id != data.getValue(table.getSelection()[0].row, 4))
          return;
        if (json == null) {
          mbdiv.html('<center><img src="http://s3.cuetools.net/face-sad.png" alt="No metadata found"></center>');
          return;
        }
        mbdata = ctdbMetaData(json);
        var mbview = new google.visualization.DataView(mbdata);
        mbview.hideColumns([7,8,10,11,12]); 
        mbtable.draw(mbview, {allowHtml: true, width: 1200, page: 'enable', pageSize: <?php echo isset($where_id) ? 10 : 5; ?>, sort: 'disable', showRowNumber: false});
        resetCoverart();
      }
    });
  }

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
    table.setSelection();
    resetMetadata();
    data = ctdbEntryData(xmlhttp.responseText);
    start += shift;
    prev = start != 0;
    next = data.getNumberOfRows() >= <?php echo $count?>;
    opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
    var view = new google.visualization.DataView(data);
    view.hideColumns([6,7,8]);
    table.draw(view, opts);
    mbdiv.hide();
    if (sbdiv != null) sbdiv.innerHTML = '';
  });
 
  if (mbdiv.length) {
    google.visualization.events.addListener(table, 'select', resetMetadata); 
    google.visualization.events.addListener(mbtable, 'select', resetCoverart);
  }

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
        var sbopts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, page: 'enable', pageSize: 5};
        var sbview = new google.visualization.DataView(sbdata);
        sbview.hideColumns([5,6,7,8,11,12]);
        sbtable.draw(sbview, sbopts);
      }
      xmlhttp.send(null);
    });
  var view = new google.visualization.DataView(data);
  view.hideColumns([6,7,8]);
  table.draw(view, opts);
  //document.getElementById('entries_div').children[0].children[0].style["box-shadow"] = "rgb(204, 204, 204) 3px 3px 5px";
  <?php if (isset($where_id)) { ?>
  table.setSelection([{row:0}]);
  resetMetadata();
  $("#entries_div").hide();
  <?php } ?>
};
function setMetadata(strargs) {
  var admdiv = document.getElementById('admin_div');
  var args = JSON.parse(decodeURIComponent(strargs));
  admdiv.innerHTML = JSON.stringify(args);
};
</script>
