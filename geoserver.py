#!/usr/bin/env python
import sys
import os
import csv
from geoserver.catalog import Catalog
import httplib2
from urlparse import urlparse


#~ FILENAME
#~ WORKSPACE
#~ URI
#~ LAYER_NAME
#~ LAYER_TITLE
#~ LAYER_ABSTRACT
#~ LAYER_KEYWORDS
#~ ATTRIBUTION_TEXT
#~ ATTRIBUTION_LINK
#~ LOGO_URL
#~ LOGO_W
#~ LOGO_H
#~ LOGO_TYPE

USERNAME = "xxxxxx"
PASSWORD = "xxxxxx"
REST_URL = "https://server.org/geoserver/rest/"
BASE_PATH = "/var/local/geodata/"

SIREN = 'FR-xxxxxxxx-'

# loads table definition's csv
def load_csv(csv_file):
    rs = []
    defs = csv.reader(open(csv_file, 'rb'), delimiter=';')
    for row in defs:
        rs.append(map(lambda s: s.strip(" '"), row))
    return rs

rows = load_csv('file.csv')


cat = Catalog(REST_URL, username=USERNAME, password=PASSWORD)


def get_unique_workspaces(rows):
    ws = []
    for row in rows:
        if not row[1] in ws:
            ws.append(row[1])
    return ws

workspaces = get_unique_workspaces(rows)

#~ print workspaces

def geoserver_ws(ws):
    for workspace in workspaces: #set(ws):
        w = cat.get_workspace(workspace)
        if w is not None:
            cat.delete(w, True, True)
        cat.create_workspace(workspace, 'https://server.org/geoserver/'+workspace+'/')


print '\033[1;34mDrop and create workspaces in GeoServer\033[1;m'
geoserver_ws(workspaces)
cat.reload()


# Geoserver resources {{{
tmpl = '<?xml version="1.0"?>\
<coverageStore>\
  <url>file://{file}</url>\
  <type>GeoTIFF</type>\
  <enabled>true</enabled>\
  <name>{store}</name>\
  <workspace>\
    <name>{workspace}</name>\
  </workspace>\
</coverageStore>'

http = httplib2.Http(disable_ssl_certificate_validation=True)
http.add_credentials(USERNAME, PASSWORD)
netloc = urlparse(REST_URL).netloc
http.authorizations.append(
    httplib2.BasicAuthentication(
        (USERNAME, PASSWORD),
        netloc,
        REST_URL,
        {},
        None,
        None,
        http
        ))
headers = {
    "Content-type": "text/xml",
    "Accept": "application/xml"
}


def geoserver_datastores(rows):
    for row in rows:
        ws = row[1]
        store_name = row[3]
        file_name = row[0]
        print ('Creating %s datastore' % store_name).ljust(70, ' '),
        # does not work due to https://github.com/boundlessgeo/gsconfig/issues/95 !
        #~ ds = cat.create_coveragestore2(store_name, ws)
        #~ ds.data_url = "file:///var/cache/sig/data/"+file_name
        #~ ds.type = "GeoTIFF"
        #~ ds.dirty.update(url = ds.data_url)
        #~ cat.save(ds)
        #~ print '\033[1;32mDONE\033[1;m'

        response = http.request(
                REST_URL+'workspaces/'+ws+'/coveragestores?name='+store_name,
                'POST',
                tmpl.format(store=store_name, workspace=ws, file=BASE_PATH + file_name),
                headers)
        if "'201'" in str(response[0]):
            print '\033[1;32mDONE\033[1;m'
        else:
            print '\033[91mFAIL\033[1;m' + ' ' + response[1]


print '\033[1;34mCreating datastore in GeoServer\033[1;m'
geoserver_datastores(rows)


#~ 0 FILENAME
#~ 1 WORKSPACE
#~ 2 URI
#~ 3 LAYER_NAME
#~ 4 LAYER_TITLE
#~ 5 LAYER_ABSTRACT
#~ 6 LAYER_KEYWORDS
#~ 7 ATTRIBUTION_TEXT
#~ 8 ATTRIBUTION_LINK
#~ 9 LOGO_URL
#~ 10 LOGO_W
#~ 11 LOGO_H
#~ 12 LOGO_TYPE


layer_tmpl = '<?xml version="1.0"?>\
<layer>\
    <projectionPolicy>FORCE_DECLARED</projectionPolicy>\
    <opaque>false</opaque>\
    <queryable>false</queryable>\
    <attribution>\
        <title>{attr_text}</title>\
        <href>{attr_link}</href>\
        <logoURL>{logo_url}</logoURL>\
        <logoWidth>{logo_width}</logoWidth>\
        <logoHeight>{logo_height}</logoHeight>\
        <logoType>{logo_type}</logoType>\
    </attribution>\
</layer>'


coverage_tmpl = '<?xml version="1.0"?>\
<coverage>\
    <nativeName>{layer}</nativeName>\
    <title>{title}</title>\
    <abstract>{abstract}</abstract>\
    <keywords>{keywords_string}</keywords>\
    <enabled>true</enabled>\
    <projectionPolicy>FORCE_DECLARED</projectionPolicy>\
    <metadataLinks>\
        <metadataLink>\
            <type>text/html</type>\
            <metadataType>TC211</metadataType>\
            <content>https://server.org/geonetwork/apps/georchestra/?uuid={uuid}</content>\
        </metadataLink>\
        <metadataLink>\
            <type>text/xml</type>\
            <metadataType>TC211</metadataType>\
            <content>https://server.org/geonetwork/srv/fre/xml_iso19139?uuid={uuid}</content>\
        </metadataLink>\
        <metadataLink>\
            <type>text/html</type>\
            <metadataType>ISO19115:2003</metadataType>\
            <content>https://server.org/geonetwork/apps/georchestra/?uuid={uuid}</content>\
        </metadataLink>\
        <metadataLink>\
            <type>text/xml</type>\
            <metadataType>ISO19115:2003</metadataType>\
            <content>https://server.org/geonetwork/srv/fre/xml_iso19139?uuid={uuid}</content>\
        </metadataLink>\
    </metadataLinks>\
    <metadata>\
        <entry key="cacheAgeMax">31536000</entry>\
        <entry key="cachingEnabled">true</entry>\
    </metadata>\
    <parameters>\
      <entry>\
        <string>InputTransparentColor</string>\
        <string>#000000</string>\
      </entry>\
      <entry>\
        <string>SUGGESTED_TILE_SIZE</string>\
        <string>512,512</string>\
      </entry>\
    </parameters>\
</coverage>'

def geoserver_layers(rows):
    for row in rows:
        ws = row[1]
        layer_name = row[3]
        store_name = row[3]
        file_name = row[0]
        keywords = '<string>'+('</string><string>'.join(row[6].split(', ')))+'</string>'
        print ('Creating %s layer' % layer_name).ljust(70, ' '),


        # dirty hack: because of https://jira.codehaus.org/browse/GEOS-6874
        command = "curl --insecure -u "+USERNAME+":"+PASSWORD+" -XPUT -H \"Content-type: text/plain\" -d \"file://"+BASE_PATH+row[0]+"\" '"+REST_URL+"workspaces/"+ws+"/coveragestores/"+layer_name+"/external.geotiff?configure=first&coverageName="+layer_name+"'"
        os.system(command)


        spec = layer_tmpl.format(attr_text = row[7], attr_link = row[8], logo_url = row[9], logo_width = row[10], logo_height = row[11], logo_type = row[12])
        command = "curl --insecure -u "+USERNAME+":"+PASSWORD+" -XPUT -H 'Content-type: text/xml' -d '"+spec+"' "+REST_URL+"layers/"+ws+":"+layer_name+".xml"
        os.system(command)

        # we're hitting this issue https://jira.codehaus.org/browse/GEOS-6874 if we create the coverage from the beginning with this one:
        response = http.request(
                REST_URL+'workspaces/'+ws+'/coveragestores/'+store_name+'/coverages/'+layer_name+".xml",
                'PUT',
                coverage_tmpl.format(layer=layer_name, title=row[4], abstract=row[5], keywords_string=keywords, uuid=SIREN+layer_name),
                headers)
        if "'200'" in str(response[0]):
            print '\033[1;32mDONE\033[1;m'
        else:
            print '\033[91mFAIL\033[1;m' + ' ' + response[1]


print '\033[1;34mCreating layers in GeoServer\033[1;m'
geoserver_layers(rows)
