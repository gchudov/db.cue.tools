<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript' src="http://s3.cuetools.net/ctdb10.js"></script>
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
    view.hideColumns([6,7]);
    table.draw(view, opts);
    if (mbdiv != null) mbdiv.innerHTML = '';
    if (sbdiv != null) sbdiv.innerHTML = '';
  });
  if (mbdiv != null)
    google.visualization.events.addListener(table, 'select', function() {
      if (table.getSelection().length == 0) {
        mbdiv.innerHTML = '';
        return;
      }
      var srow = table.getSelection()[0].row;
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.open("GET", '/lookup2.php?type=json&ctdb=0&musicbrainz=1&freedb=2&freedbfuzzy=3&fuzzy=1&toc=' + data.getValue(srow, 7), true);
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
        var mbdata = ctdbMetaData(xmlhttp.responseText);
        xmlhttp = null;
        var mbview = new google.visualization.DataView(mbdata);
        mbview.hideColumns([8,9]); 
        mbtable.draw(mbview, {allowHtml: true, width: 1200, page: 'enable', pageSize: 5, sort: 'disable', showRowNumber: false});
      }
      xmlhttp.send(null);
    });
  if (mbdiv != null)
    google.visualization.events.addListener(mbtable, 'select', function() {
      var admdiv = document.getElementById('admin_div');
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
      admdiv.innerHTML = '<a onClick="setMetadata(&quot;' + encodeURIComponent(JSON.stringify(admargs)) +  '&quot;)">set</a>';
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
  view.hideColumns([6,7]);
  table.draw(view, opts);
};
function setMetadata(strargs) {
  var admdiv = document.getElementById('admin_div');
  var args = JSON.parse(decodeURIComponent(strargs));
  admdiv.innerHTML = JSON.stringify(args);
};
</script>
