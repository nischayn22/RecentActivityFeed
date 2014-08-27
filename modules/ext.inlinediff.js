/*
 * Borrowed from https://en.wikipedia.org/wiki/User:Writ_Keeper/Scripts/commonHistory.js
 * as per http://www.mediawiki.org/wiki/Editor_campaigns/Activity_feed
 */
(function( $ ) {

diffRequestLocked = "f";
if(typeof inspectText == "undefined")
{
  inspectText = "inspect diff";
}
if(typeof showText == "undefined")
{
  showText = "show diff";
}
if(typeof hideText == "undefined")
{
  hideText = "hide diff";
}
  function inspectionEachHelper(index, element)
  {
    var findString;
    if(wgAction == "history" || $(element).hasClass("mw-enhanced-rc-nested"))
    {
      findString = 'a:contains("prev")';
    }
    else
    {
      findString = 'a:contains("diff")';
    }

    var regex;

    if(wgCanonicalSpecialPageName == "Contributions")
    {
      regex = /&oldid=(\d+)$/;

    }
    else
    {
      regex = /&diff=(\d+)&oldid=/;
    }
    var diffLink = $(element).find(findString);
    if(diffLink.length > 0 && !(/(\.js|\.css)&/.test(diffLink[0].href)))
    {
      var regexResult = regex.exec(diffLink[0].href);
      if(regexResult != null && regexResult.length >= 2)
      {
        var diffID = regexResult[1];
        var inlineDiffButton;
        if(typeof inlineDiffBigUI === "undefined")
        {
          inlineDiffButton = document.createElement("a");
          inlineDiffButton.href = "#";
          inlineDiffButton.innerHTML = '<b><span style="color:black;"> [</span><span style="color:#339900;">'+inspectText+'</span><span style="color:black;">] </span></b>';
        }
        else
        {
          inlineDiffButton = document.createElement("input");
          inlineDiffButton.type = "button";
          inlineDiffButton.value = "Inspect edit";
        }
        inlineDiffButton.id = diffID;
        $(inlineDiffButton).click(function(){ return inspectWatchlistDiff(this);});
        $(element).find('.comment').append(inlineDiffButton);
      }
    }
  }
  function addWatchlistInspectionBoxes() {


    var entries = $("#mw-content-text table.mw-enhanced-rc");
    if(entries.length == 0)
    {
      $(".mw-changeslist").each(function(ind, el)
                                    {
                                      $(el).children("div").each(inspectionEachHelper);
                                    });
    }
    else
    {
      entries.each(inspectionEachHelper);
      $("td.mw-enhanced-rc-nested").each(inspectionEachHelper);
    }
    mw.loader.load('mediawiki.action.history.diff');
  }

  function inspectWatchlistDiff(button)
  {
    if(diffRequestLocked === "t")
    {
      alert("An old request is still being processed, please wait...");
      return false;
    }
    else
    {
      diffRequestLocked = "t";
      $.getJSON("/w/api.php?action=query&prop=revisions&format=json&rvprop=timestamp&rvdiffto=prev&revids="+button.id, function(response, status)
                {
                  if(response == null)
                  {
                    alert("Request failed!");
                    diffRequestLocked = "f";
                    return false;
                  }

                  var diffString = response.query.pages[Object.keys(response.query.pages)[0]].revisions[0].diff["*"];

                  if(diffString == null)
                  {
                    alert("Request failed!");
                    diffRequestLocked = "f";
                    return false;
                  }

                  var newTable = document.createElement("table");
                  newTable.className = "diff";
                  $(newTable).html('<colgroup><col class="diff-marker"><col class="diff-content"><col class="diff-marker"><col class="diff-content"></colgroup>');

                  $(newTable).append(diffString);
                  if($("#"+ button.id).parent("td").length > 0 && !($("#"+ button.id).parent("td").hasClass("mw-enhanced-rc-nested")))
                  {
                    $("#"+ button.id).parents("table.mw-enhanced-rc:first").after(newTable);
                  }
                  else
                  {
                    $(newTable).insertAfter("#"+ button.id);
                  }
                  newTable.id = button.id + "display";

                  $(button).unbind("click");
                  if(typeof inlineDiffBigUI === "undefined")
                  {
                    $(button).html('<b><span style="color:black;"> [</span><span style="color:#339900;">'+hideText+'</span><span style="color:black;">] </span></b>');
                    $(button).click(function(){ return hideSmallEditInspection(this);});
                  }
                  else
                  {
                    $(button).attr("value","Hide edit");
                    $(button).click(function(){ return hideEditInspection(this);});
                  }

                  diffRequestLocked = "f";
                });

    }
    return false;
  }

  function showEditInspection(button)
  {
    $("#"+button.id+"display").css("display", "");
    $(button).attr("value","Hide edit");
    $(button).unbind("click");
    $(button).click(function(){ return hideEditInspection(this);});
    return false;
  }

  function hideEditInspection(button)
  {
    $("#"+button.id+"display").css("display", "none");
    $(button).attr("value","Show edit");
    $(button).unbind("click");
    $(button).click(function(){ return showEditInspection(this);});
    return false;
  }

  function showSmallEditInspection(button)
  {
    $("#"+button.id+"display").css("display", "");
    $(button).html('<b><span style="color:black;"> [</span><span style="color:#339900;">'+hideText+'</span><span style="color:black;">] </span></b>');
    $(button).unbind("click");
    $(button).click(function(){ return hideSmallEditInspection(this);});
    return false;
  }

  function hideSmallEditInspection(button)
  {
    $("#"+button.id+"display").css("display", "none");
    $(button).html('<b><span style="color:black;"> [</span><span style="color:#339900;">'+showText+'</span><span style="color:black;">] </span></b>');
    $(button).unbind("click");
    $(button).click(function(){ return showSmallEditInspection(this);});
    return false;
  }

  $(document).ready(addWatchlistInspectionBoxes);
//  $(document).ready(alert);
})( window.jQuery );
