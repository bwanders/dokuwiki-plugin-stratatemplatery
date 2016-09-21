Strata Templatery
=================

This plugin depends on [bwanders/dokuwiki-strata](https://github.com/bwanders/dokuwiki-strata), and [bwanders/dokuwiki-plugin-templatery](https://github.com/bwanders/dokuwiki-plugin-templatery).

This plugin combines the Templatery plugin with the Strata plugin to allow templated display and entry of data, and typed template invocations.


Example
=======

These examples assume knowledge of the [bwanders/dokuwiki-plugin-templatery](https://github.com/bwanders/dokuwiki-plugin-templatery) plugin syntax.

Strata Templatery adds three features to the templatery plugin: typed fields, templated data, and query views.


Typed Fields
------------

With Strata Templatery you can use types in "vanilla" templates as well. This allows you to display
nicer templates without extra effort.

For example

    <template typed>
    An example: @@a[wiki]@@
    With explicit type: @@b@@
    </template>

Types can be given in either the template, or the invocation. The following invocation gives `b` a `[page]` type,
making the `playground:playground` a page reference:

    {{template>demo#typed
    |A=5 ^_^ + 7 8-)
    |b[page]=playground:playground
    }}

Note that type hints from invocations override type hints in templates.


Templated Data
--------------

Strata data with a class will automatically be rendered with a template.

For example:

    <data person #Alice>
    Mood: Happy
    Knows [ref]*: #Bob, #Donna
    </data>

If the template `template:person` exists, it will be displayed instead of the default table-based display. Inside
the template, you can use all fields declared by the data entry (`@@Knows@@` and `@@Mood@@` in this example). Next to these
explicit fields, there are some automatically generated fields available:

  - `@@entry title@@` the entry title of the data entry
  - `@@is a@@` the 'class' of the data entry, `person` in this case
  - `@@.subject@@` a reference to the current data entry
  - `@@.next@@` a link to the next data entry with the same name (useful if you normally split up your data entries into multiple parts)
  - `@@.previous@@` a link to the previous data entry with the same name

In the case of the example data, a simple template could be:

    <template>
    <entry>
    ==== @@entry title@@ ====
    //Knows: @@knows@@//

    Is in a @@mood@@ mood.
    </entry>
    </template>

Notice the `<entry>` tags surrounding the entry. These are optional, but enhance the functionality of the template by
allowing the wiki to create links to the data entry (which is how the `.next` and `.previous` fields work).

Furthermore, you can use the special `@@->fields@@` marker to display a table of all fields that were in the entry
but that were not used somewhere in the template. This allows you to keep the flexibility of custom fields, while
still using a template to improve the display. Fields can be excluded from this dynamic display with `@@->fields|exclude=field1, field2, field3@@`, or the set of fields can be restricted with `@@->fields|only=field1, field2, field3@@`. Multiple `@@->fields@@` markers are allowed, and can use different restrictions.

Query Views
-----------

In Strata, you can query data and get an overview of the results. With Strata Templayery, you can also use
templates to display the overview.


### Simple View

The simplest variant of this is by using one template invocation per result item:

    <view ?name ?knows>
    template {
      demo
    }

    ?p is a: person
    ?p entry title: ?name
    ?p Knows [ref]: ?knows

    group {
      ?p
      ?name
    }
    </view>

The query is written in the same way as with a normal Strata `<list>` or `<table>`, but by using `<view>`.
The `template` group describes the actual template to use, which in this case will be `template:demo`.

For each result, the template will be repeated. The fields `p`, `name` and `knows` will be made available as
they are the result fields of the query.

A simple template for displaying this query would be:

    <template>
    @@name@unique[text]@@ knows //@@knows@@//
    </template>

Notice that we can use the normal type hints (`[text]`) and aggregates (`@unique`) to determine how the template
should handle the output fields.


### List View

It is possible to produce templated lists, by using `<view:list>` instead of `<view>` as the opening tag. In this case
the template that is used should contain only list items. For (a contrived) example:

    <view:list ?s ?a ?b ?c>
    template {
      listtest
    }
    
    ?s A: ?a
    ?s B[ref]: ?b
    optional {
      ?s C: ?c
    }
    </view>

Could use the template:

    <template>
      * @@s[ref]@@
        * @@a@@ <*if C>(@@c@@)</if>
        * See also: @@b@@
    </template>

As long as the template starts as a list, ends as a list and does not have something non-list-like in between, it can be used as a list view template.


### Table View

It is also possible to produce a templated table. To do so, use `<view:table>` as the opening tag. In this case the first template on the indicated template page can define one or more rows that will be repeated for each result item. Furthermore, if the template page contains templates called `header` or `footer`, these will be used as the table header and footer.

For example:

    <view:table ?s ?a ?b ?c>
    template {
      tabletest
    }
    -- query omitted
    </view>

The example will use the `template:tabletest` page as template page, and will use the first template as the row template. If 
the `template:tabletest#header` exists, it will be used as the table header, of `template:tabletest#footer` exists, it will be
used as the table footer.

If two template names are given, the first will be used as the table header, and the second for the table rows.

If three template names are given, the first will be used as table header, the second as table rows, and the third as the footer.

For example:

    <view:table ?s ?a ?b ?c>
    template {
      header_template
      rows_template
      footer_template
    }
    -- query omitted
    </view>

Note that all three of the table view templates must each consists of a single table — preferably with the same number of columns.
