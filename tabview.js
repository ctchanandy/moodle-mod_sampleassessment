/* Call YUI to create tabs */

YUI().use("tabview", function(Y)
{
    var tabView = new Y.TabView
    (
        {
            srcNode: '#samples'
        }
    );
    tabView.render();
});