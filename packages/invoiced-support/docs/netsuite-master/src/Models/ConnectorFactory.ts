import {encode as NEncode, https as NHTTPS} from "N";
import * as NLog from "N/log";
import {InvoicedRowGenericValue, ObjectRow} from "../definitions/Row";
import {ClientResponse, RequestOptions} from "N/http";
import * as NFile from "N/file";
import {FileWrapper} from "../definitions/FileWrapper";
import {ConfigFactory, ConfigInterface} from "../Utilities/Config";
import {ReconciliationError} from "./ReconciliationError";

export interface ConnectorFactoryInterface {
    getInstance(): ConnectorInterface;
}

export interface ConnectorInterface {
    lastError: string;
    send(method: Method, endpoint: Endpoint | string, key: null | string, data?: ObjectRow | ReconciliationError): null | InvoicedApiResponse;
    sendFile(files: FileWrapper[], key: null | string): null | InvoicedApiResponse
}

export type InvoicedApiResponse = {
    id: number,
    [key: string]: InvoicedRowGenericValue,
}

type MimeTypes = {
    [key in NFile.Type]: string
};


type Method = "POST" | "GET" | "PATCH";
export type Endpoint = "customers/accounting_sync" | "invoices/accounting_sync" | "credit_notes/accounting_sync" | "payments/accounting_sync" | "reconciliation_errors";
type Header = {
    'Content-Type'?: string,
    'Authorization'?: string,
    'XNetSuiteBundleVersion'?: string,
};

type defineCallback = (
    https: typeof NHTTPS,
    encode: typeof NEncode,
    config: ConfigFactory,
    log: typeof NLog,
    file: typeof NFile,
    global: typeof Globals
) => ConnectorFactoryInterface;


declare function define(arg: string[], fn: defineCallback): ConnectorInterface

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['N/https', 'N/encode', 'tmp/src/Utilities/Config', 'N/log', 'N/file', 'tmp/src/Global'], function (https, encode, configFactory, log, file, global) {
    let instance: ConnectorInterface;
    class ConnectorSingleton {
        public lastError: string = '';
        private readonly apiKey: string;
        private readonly baseUrl: string;
        private readonly types: MimeTypes;
        private config: ConfigInterface;

        constructor() {
            this.config = configFactory.getInstance();

            const types: MimeTypes = {};
            types[file.Type.AUTOCAD] = 'application/x-autocad';
            types[file.Type.BMPIMAGE] = 'image/x-xbitmap';
            types[file.Type.CSV] = 'text/csv';
            types[file.Type.EXCEL] = 'application/vnd.ms-excel';
            types[file.Type.FLASH] = 'application/x-shockwave-flash';
            types[file.Type.GIFIMAGE] = 'image/gif';
            types[file.Type.GZIP] = 'application/?x-?gzip-?compressed';
            types[file.Type.HTMLDOC] = 'text/html';
            types[file.Type.ICON] = 'image/ico';
            types[file.Type.JAVASCRIPT] = 'text/javascript';
            types[file.Type.JPGIMAGE] = 'image/jpeg';
            types[file.Type.JSON] = 'application/json';
            types[file.Type.MESSAGERFC] = 'message/rfc822';
            types[file.Type.MP3] = 'audio/mpeg';
            types[file.Type.MPEGMOVIE] = 'video/mpeg';
            types[file.Type.MSPROJECT] = 'application/vnd.ms-project';
            types[file.Type.PDF] = 'application/pdf';
            types[file.Type.PJPGIMAGE] = 'image/pjpeg';
            types[file.Type.PLAINTEXT] = 'text/plain';
            types[file.Type.PNGIMAGE] = 'image/x-png';
            types[file.Type.POSTSCRIPT] = 'application/postscript';
            types[file.Type.POWERPOINT] = 'application/?vnd.?ms-?powerpoint';
            types[file.Type.QUICKTIME] = 'video/quicktime';
            types[file.Type.RTF] = 'application/rtf';
            types[file.Type.SMS] = 'application/sms';
            types[file.Type.STYLESHEET] = 'text/css';
            types[file.Type.TIFFIMAGE] = 'image/tiff';
            types[file.Type.VISIO] = 'application/vnd.visio';
            types[file.Type.WORD] = 'application/msword';
            types[file.Type.XMLDOC] = 'text/xml';
            types[file.Type.ZIP] = 'application/zip';
            this.types = types;

            this.apiKey = this.config.getApiKey();
            log.audit('companyInfo', this.config);
            this.baseUrl = 'https://api.invoiced.com/';
            if (this.config.isStaging()) {
                this.baseUrl = 'https://api.staging.invoiced.com/';
            } else if (this.config.isSandbox()) {
                this.baseUrl = 'https://api.sandbox.invoiced.com/';
            }
        }

        private static toBase64(str: string): string {
            return encode.convert({
                string: str,
                inputEncoding: encode.Encoding.UTF_8,
                outputEncoding: encode.Encoding.BASE_64,
            });
        }

        public send(method: Method, endpoint: Endpoint | string, key?: string, data?: ObjectRow | ReconciliationError): null | InvoicedApiResponse {
            //populating static variable
            const header: Header = this.defaultHeader(key);
            header['Content-Type'] = 'application/json;charset=utf-8';
            if (method === "PATCH") {
                method = "POST";
                // @ts-ignore
                header['X-HTTP-Method-Override'] = 'PATCH';
            }
            log.debug('method', method);

            let request: RequestOptions = {
                url: this.baseUrl + endpoint,
                headers: header,
                method: method,
            };
            log.debug('request', request);

            // GET do not have request bodies
            if (method !== 'GET' && typeof data !== 'undefined') {
                request.body = JSON.stringify(data);
            }

            const response = https.request(request);
            return this.handleResponse(response);
        };

        private handleResponse(response: ClientResponse): null | InvoicedApiResponse {
            const body = response.body;
            const code = response.code;
            log.debug({
                title: 'Response',
                details: {
                    body: body,
                    code: code,
                },
            });

            if (code >= 200 && code < 300) {
                return JSON.parse(body);
            }

            let message = null;
            try {
                const result = JSON.parse(body);
                message = result.message;
            } catch (e) {
                // do nothing
            }

            if (!message) {
                message = 'An unknown error has occurred.';
            }

            log.error('Invoiced API error', message);
            this.lastError = message;
            return null;
        };

        public sendFile(files: FileWrapper[], key?: string): null | InvoicedApiResponse {
            const boundary = 'someuniqueboundaryasciistring';
            const headers: Header = this.defaultHeader(key);
            headers['Content-Type'] = 'multipart/form-data;charset=ISO-8859-1; boundary=' + boundary;
            // Body
            const body: string[] = [];
            const that = this;
            files.forEach(function (p, idx) {
                const partIsFile = ConnectorSingleton.isFile(p.value);
                body.push('--' + boundary);
                body.push(
                    'Content-Disposition: form-data; name="' +
                        p.name +
                        '"' +
                        (partIsFile ? '; filename="' + p.value.name + '"' : ''),
                );
                if (partIsFile) {
                    body.push(that.getContentType(p.value));
                }
                body.push('');
                body.push(p.value.getContents());
                if (idx === files.length - 1) {
                    body.push('--' + boundary + '--');
                    body.push('');
                }
            });
            // Submit Request
            try {
                const response = https.post({
                    url: this.baseUrl + 'files?base64=1',
                    headers: headers,
                    body: body.join('\r\n'),
                });
                return this.handleResponse(response);
            } catch (err) {
                const e = err as Error;
                log.error(
                    'Failed to submit file',
                    (e.message || e.toString()),
                );
            }
            return null;
        };

        private getContentType(f: NFile.File): string {
            const mime = this.types[f.fileType];
            const charset = f.encoding;
            const ct = 'Content-Type: ' + mime + (charset ? ';charset=' + charset : '');
            log.debug('content for ' + f.name, ct);
            return ct;
        };

        private static isFile(o: any) {
            return typeof o === 'object' && typeof o.fileType !== 'undefined';
        };

        public defaultHeader(key?: string): Header {
            const apiKey = ConnectorSingleton.toBase64((key || this.apiKey) + ':');
            return {
                Authorization: 'Basic ' + apiKey,
                XNetSuiteBundleVersion: global.BUNDLE_VERSION,
            };
        };
    }
    return {
        getInstance: () => {
            if (instance == null) {
                instance = new ConnectorSingleton();
            }
            return instance;
        },
    };
});
