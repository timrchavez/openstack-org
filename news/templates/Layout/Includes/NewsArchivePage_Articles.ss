<% loop $ArchivedNews %>
    <div class="recentBox">
        <div class="recentHeadline">
            <a href="news/view/$ID/$HeadlineForUrl?ar=1">$RAW_val(Headline)</a> <span class="itemTimeStamp">$formatDate</span>
        </div>
        <div class="recentSummary">$HTMLSummary</div>
    </div>
<% end_loop %>