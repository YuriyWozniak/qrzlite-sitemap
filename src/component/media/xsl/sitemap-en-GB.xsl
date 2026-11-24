<?xml version="1.0" encoding="UTF-8"?>
<!-- QrzLite Sitemap: shows sitemap.xml as a table in a browser. Search engines ignore it. -->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
                exclude-result-prefixes="s">
    <xsl:output method="html" encoding="UTF-8" indent="yes" doctype-system="about:legacy-compat"/>

    <xsl:param name="lang">en</xsl:param>
    <xsl:param name="title">Sitemap</xsl:param>
    <xsl:param name="urls">URLs</xsl:param>
    <xsl:param name="files">Sitemap files</xsl:param>
    <xsl:param name="address">Address</xsl:param>
    <xsl:param name="modified">Last modified</xsl:param>
    <xsl:param name="note">This file is for search engines.</xsl:param>

    <xsl:template match="/">
        <html lang="{$lang}">
            <head>
                <meta charset="UTF-8"/>
                <meta name="robots" content="noindex"/>
                <title><xsl:value-of select="$title"/></title>
                <style>
                    body {
                        font: 15px/1.5 system-ui, sans-serif;
                        margin: 2rem auto;
                        max-width: 70rem;
                        padding: 0 1rem;
                        color: #222;
                    }

                    h1 {
                        font-size: 1.6rem;
                        margin-bottom: 0.25rem;
                    }

                    p {
                        color: #666;
                        margin-top: 0;
                    }

                    table {
                        border-collapse: collapse;
                        width: 100%;
                    }

                    th,
                    td {
                        text-align: left;
                        padding: 0.35rem 0.6rem;
                        border-bottom: 1px solid #e3e3e3;
                        vertical-align: top;
                    }

                    th {
                        background: #f4f4f4;
                    }

                    td.n {
                        color: #999;
                        text-align: right;
                        width: 1%;
                    }

                    td.d {
                        white-space: nowrap;
                        width: 1%;
                        color: #555;
                    }

                    a {
                        color: #0b5cad;
                        text-decoration: none;
                        word-break: break-all;
                    }

                    a:hover {
                        text-decoration: underline;
                    }
                </style>
            </head>
            <body>
                <xsl:apply-templates/>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="s:urlset">
        <h1><xsl:value-of select="$title"/></h1>
        <p><xsl:value-of select="$urls"/>: <xsl:value-of select="count(s:url)"/>. <xsl:value-of select="$note"/></p>
        <table>
            <thead>
                <tr><th scope="col">#</th><th scope="col"><xsl:value-of select="$address"/></th><th scope="col"><xsl:value-of select="$modified"/></th></tr>
            </thead>
            <tbody>
                <xsl:for-each select="s:url">
                    <tr>
                        <td class="n"><xsl:value-of select="position()"/></td>
                        <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                        <td class="d"><xsl:value-of select="concat(substring(s:lastmod, 1, 10), ' ', substring(s:lastmod, 12, 5))"/></td>
                    </tr>
                </xsl:for-each>
            </tbody>
        </table>
    </xsl:template>

    <xsl:template match="s:sitemapindex">
        <h1><xsl:value-of select="$title"/></h1>
        <p><xsl:value-of select="$files"/>: <xsl:value-of select="count(s:sitemap)"/>. <xsl:value-of select="$note"/></p>
        <table>
            <thead>
                <tr><th scope="col">#</th><th scope="col"><xsl:value-of select="$address"/></th><th scope="col"><xsl:value-of select="$modified"/></th></tr>
            </thead>
            <tbody>
                <xsl:for-each select="s:sitemap">
                    <tr>
                        <td class="n"><xsl:value-of select="position()"/></td>
                        <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                        <td class="d"><xsl:value-of select="concat(substring(s:lastmod, 1, 10), ' ', substring(s:lastmod, 12, 5))"/></td>
                    </tr>
                </xsl:for-each>
            </tbody>
        </table>
    </xsl:template>
</xsl:stylesheet>
