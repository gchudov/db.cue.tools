<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript'>
google.setOnLoadCallback(drawTable);
function drawTable() 
{
  var data = new google.visualization.DataTable(<?php echo $json_entries?>);
  var table = new google.visualization.Table(document.getElementById('entries_div'));
  var opts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, pageSize: <?php echo $count?>, page: 'event', pagingButtonsConfiguration : 'both'};
  var start = <?php echo $start?>;
  var prev = start != 0;
  var next = data.getNumberOfRows() >= <?php echo $count?>;
  var mbdiv = document.getElementById('musicbrainz_div');
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
    data = new google.visualization.DataTable(xmlhttp.responseText);
    start += shift;
  prev = start != 0;
  next = data.getNumberOfRows() >= <?php echo $count?>;
  opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
    var view = new google.visualization.DataView(data);
    view.hideColumns([6]);
    table.draw(view, opts);
    if (mbdiv != null) mbdiv.innerHTML = '';
  });
  if (mbdiv != null)
    google.visualization.events.addListener(table, 'select', function() {
      if (table.getSelection().length == 0) {
        mbdiv.innerHTML = '';
        return;
      }
      var srow = table.getSelection()[0].row;
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.open("GET", '/mbjson.php?mbid=' + data.getValue(srow, 6), true);
      mbdiv.innerHTML = '<img src="hourglass.png" alt="Looking up metadata...">';
      xmlhttp.onreadystatechange=function() {
        if (xmlhttp.readyState != 4 || xmlhttp.status == 0) return;
        if (xmlhttp.status == 404) {
          mbdiv.innerHTML = 'No metadata found';
          xmlhttp = null;
          return;
        }
        if (xmlhttp.status != 200) {
          mbdiv.innerHTML = xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText;
          xmlhttp = null;
          return;
        }
        var mbdata = new google.visualization.DataTable(xmlhttp.responseText);
        xmlhttp = null;
        for (var row = 0; row < mbdata.getNumberOfRows(); row++)
          mbdata.setProperty(row, 7, 'style', 'font-family:courier; text-align:right;');
        var formatter = new google.visualization.TablePatternFormat('<a target=_blank href="http://musicbrainz.org/release/{1}">{0}</a>');
        formatter.format(mbdata, [2, 8], 2); 
        var mbview = new google.visualization.DataView(mbdata);
        mbview.hideColumns([8]); 
        var mbtable = new google.visualization.Table(mbdiv);
        mbtable.draw(mbview, {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false});
      }
      xmlhttp.send(null);
    });

  var view = new google.visualization.DataView(data);
  view.hideColumns([6]);
  table.draw(view, opts);
}
</script>
