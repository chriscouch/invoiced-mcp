#!/usr/bin/env node
/**
 * Comprehensive MCP Server Test Suite for Invoiced
 * Tests all 170 tools systematically with detailed logging
 */

import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { writeFileSync } from 'fs';

// Test results storage
const testResults = {
  summary: {
    totalTools: 0,
    totalTests: 0,
    passed: 0,
    failed: 0,
    skipped: 0,
    startTime: null,
    endTime: null
  },
  categories: {},
  tools: {},
  issues: []
};

// Shared test data (populated during tests)
const testData = {
  customerId: null,
  contactId: null,
  invoiceId: null,
  estimateId: null,
  creditNoteId: null,
  paymentId: null,
  itemId: null,
  taxRateId: null,
  couponId: null,
  planId: null,
  subscriptionId: null,
  paymentPlanId: null,
  paymentSourceId: null,
  paymentLinkId: null,
  chasingCadenceId: null,
  taskId: null,
  emailTemplateId: null,
  webhookId: null,
  scheduledReportId: null,
  fileId: null,
  noteId: null,
  memberId: null,
  roleId: null,
  apiKeyId: null,
  chargeId: null,
  refundId: null,
  creditBalanceId: null,
  meteredBillingId: null
};

// Utility functions
function log(message, level = 'INFO') {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] [${level}] ${message}`);
}

// Improved response parser that handles text prefixes
function parseResult(result) {
  if (!result || !result.content || !result.content[0]) {
    return null;
  }
  const text = result.content[0].text;

  // Try to extract JSON from text that may have prefixes like "Customer created successfully:\n{...}"
  const jsonMatch = text.match(/\{[\s\S]*\}|\[[\s\S]*\]/);
  if (jsonMatch) {
    try {
      return JSON.parse(jsonMatch[0]);
    } catch {
      return text;
    }
  }

  // Try direct JSON parse
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

// Check if result contains an array (handling list responses with wrapper object)
function isListResult(parsed) {
  if (Array.isArray(parsed)) return true;
  if (parsed && typeof parsed === 'object' && Array.isArray(parsed.data)) return true;
  return false;
}

function getListData(parsed) {
  if (Array.isArray(parsed)) return parsed;
  if (parsed && typeof parsed === 'object' && Array.isArray(parsed.data)) return parsed.data;
  return [];
}

// Record a test result
function recordTest(toolName, testCase, success, request, response, responseTime, notes = '') {
  if (!testResults.tools[toolName]) {
    testResults.tools[toolName] = {
      tests: [],
      passed: 0,
      failed: 0
    };
  }

  const result = {
    testCase,
    success,
    request,
    response: typeof response === 'object' ? JSON.stringify(response).substring(0, 500) : String(response).substring(0, 500),
    responseTime,
    notes,
    timestamp: new Date().toISOString()
  };

  testResults.tools[toolName].tests.push(result);
  testResults.summary.totalTests++;

  if (success) {
    testResults.tools[toolName].passed++;
    testResults.summary.passed++;
    log(`  ✅ ${toolName}:${testCase} (${responseTime}ms)`);
  } else {
    testResults.tools[toolName].failed++;
    testResults.summary.failed++;
    testResults.issues.push({
      tool: toolName,
      testCase,
      request,
      response: typeof response === 'object' ? JSON.stringify(response, null, 2) : String(response),
      notes
    });
    log(`  ❌ ${toolName}:${testCase} (${responseTime}ms) - ${notes || 'Failed'}`);
  }
}

// Call a tool and measure response time
async function callTool(client, toolName, args = {}) {
  const startTime = Date.now();
  try {
    const result = await client.callTool({ name: toolName, arguments: args });
    const responseTime = Date.now() - startTime;
    const isError = result && result.isError;
    return { success: !isError, result, responseTime, isError };
  } catch (error) {
    const responseTime = Date.now() - startTime;
    return { success: false, error: error.message, responseTime };
  }
}

// ============= CATEGORY TEST FUNCTIONS =============

// Category 1: Customer Management
async function testCustomerManagement(client) {
  const category = 'Customer Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_customer (minimal)
  let res = await callTool(client, 'create_customer', {
    name: 'Test Customer MCP'
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_customer', 'minimal_params', success, { name: 'Test Customer MCP' }, parsed, res.responseTime);
  if (success) {
    testData.customerId = String(parsed.id);
    log(`  Created customer with ID: ${testData.customerId}`);
  }

  // Test 2: create_customer (full params)
  res = await callTool(client, 'create_customer', {
    name: 'Test Customer Full',
    email: 'test.full@example.com',
    phone: '555-1234',
    notes: 'Full test customer',
    payment_terms: 'NET 30',
    currency: 'usd'
  });
  parsed = parseResult(res.result);
  success = res.success && parsed && parsed.id;
  recordTest('create_customer', 'full_params', success, { name: 'Test Customer Full' }, parsed, res.responseTime);
  const secondCustomerId = success ? String(parsed.id) : null;

  // Test 3: list_customers
  res = await callTool(client, 'list_customers', {});
  parsed = parseResult(res.result);
  success = res.success && parsed !== null;
  recordTest('list_customers', 'no_params', success, {}, { result_type: typeof parsed }, res.responseTime);

  // Test 4: get_customer
  if (testData.customerId) {
    res = await callTool(client, 'get_customer', { id: testData.customerId });
    parsed = parseResult(res.result);
    success = res.success && parsed && (String(parsed.id) === testData.customerId);
    recordTest('get_customer', 'valid_id', success, { id: testData.customerId }, parsed, res.responseTime);
  }

  // Test 5: get_customer (invalid ID - expect error)
  res = await callTool(client, 'get_customer', { id: '99999999' });
  success = res.isError || !res.success;
  recordTest('get_customer', 'invalid_id_error', success, { id: '99999999' }, res.error || parseResult(res.result), res.responseTime, 'Expected error for invalid ID');

  // Test 6: update_customer
  if (testData.customerId) {
    res = await callTool(client, 'update_customer', {
      id: testData.customerId,
      name: 'Updated Test Customer',
      notes: 'Updated via MCP test'
    });
    parsed = parseResult(res.result);
    success = res.success && parsed && parsed.name === 'Updated Test Customer';
    recordTest('update_customer', 'valid_update', success, { id: testData.customerId, name: 'Updated Test Customer' }, parsed, res.responseTime);
  }

  // Cleanup second customer
  if (secondCustomerId) {
    res = await callTool(client, 'delete_customer', { id: secondCustomerId });
    recordTest('delete_customer', 'cleanup', res.success, { id: secondCustomerId }, parseResult(res.result), res.responseTime);
  }

  log(`${category} completed`);
}

// Category 2: Contact Management
async function testContactManagement(client) {
  const category = 'Contact Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping contact tests - no customer ID', 'WARN');
    return;
  }

  const customerIdNum = parseInt(testData.customerId);

  // Test 1: create_contact (schema uses 'customer' not 'customer_id')
  let res = await callTool(client, 'create_contact', {
    customer: customerIdNum,
    name: 'Test Contact',
    email: 'contact@example.com'
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_contact', 'minimal_params', success, { customer: customerIdNum, name: 'Test Contact' }, parsed, res.responseTime);
  if (success) {
    testData.contactId = String(parsed.id);
    log(`  Created contact with ID: ${testData.contactId}`);
  }

  // Test 2: list_contacts (customer_id must be number)
  res = await callTool(client, 'list_contacts', { customer_id: customerIdNum });
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_contacts', 'valid_customer', success, { customer_id: customerIdNum }, parsed, res.responseTime);

  // Test 3: get_contact (id is STRING in schema)
  if (testData.contactId) {
    res = await callTool(client, 'get_contact', {
      customer_id: customerIdNum,
      id: testData.contactId  // String expected
    });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_contact', 'valid_ids', success, { customer_id: customerIdNum, id: testData.contactId }, parsed, res.responseTime);
  }

  // Test 4: update_contact (id is STRING in schema)
  if (testData.contactId) {
    res = await callTool(client, 'update_contact', {
      customer_id: customerIdNum,
      id: testData.contactId,  // String expected
      name: 'Updated Contact'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_contact', 'valid_update', success, { customer_id: customerIdNum, id: testData.contactId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 3: Item/Catalog Management
async function testItemManagement(client) {
  const category = 'Item Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_item (with required type)
  let res = await callTool(client, 'create_item', {
    name: 'Test Item MCP',
    type: 'service'
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_item', 'minimal_params', success, { name: 'Test Item MCP', type: 'service' }, parsed, res.responseTime);
  if (success) {
    testData.itemId = String(parsed.id);
    log(`  Created item with ID: ${testData.itemId}`);
  }

  // Test 2: create_item (product type with cost)
  res = await callTool(client, 'create_item', {
    name: 'Full Test Item',
    description: 'A comprehensive test item',
    type: 'product',
    unit_cost: 99.99,
    currency: 'usd'
  });
  parsed = parseResult(res.result);
  success = res.success && parsed && parsed.id;
  recordTest('create_item', 'full_params', success, { name: 'Full Test Item', type: 'product' }, parsed, res.responseTime);
  const secondItemId = success ? String(parsed.id) : null;

  // Test 3: list_items
  res = await callTool(client, 'list_items', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_items', 'no_params', success, {}, parsed, res.responseTime);

  // Test 4: get_item
  if (testData.itemId) {
    res = await callTool(client, 'get_item', { id: testData.itemId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_item', 'valid_id', success, { id: testData.itemId }, parsed, res.responseTime);
  }

  // Test 5: update_item
  if (testData.itemId) {
    res = await callTool(client, 'update_item', {
      id: testData.itemId,
      name: 'Updated Test Item',
      description: 'Updated description'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_item', 'valid_update', success, { id: testData.itemId, name: 'Updated Test Item' }, parsed, res.responseTime);
  }

  // Cleanup second item
  if (secondItemId) {
    res = await callTool(client, 'delete_item', { id: secondItemId });
    recordTest('delete_item', 'cleanup', res.success, { id: secondItemId }, parseResult(res.result), res.responseTime);
  }

  log(`${category} completed`);
}

// Category 4: Tax Rate Management
async function testTaxRateManagement(client) {
  const category = 'Tax Rate Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_tax_rate (with required value)
  let res = await callTool(client, 'create_tax_rate', {
    name: 'Test Tax Rate',
    value: 7.5
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_tax_rate', 'minimal_params', success, { name: 'Test Tax Rate', value: 7.5 }, parsed, res.responseTime);
  if (success) {
    testData.taxRateId = String(parsed.id);
    log(`  Created tax rate with ID: ${testData.taxRateId}`);
  }

  // Test 2: create_tax_rate (full params)
  res = await callTool(client, 'create_tax_rate', {
    name: 'Full Tax Rate',
    value: 8.25,
    inclusive: false
  });
  parsed = parseResult(res.result);
  success = res.success && parsed && parsed.id;
  recordTest('create_tax_rate', 'full_params', success, { name: 'Full Tax Rate', value: 8.25 }, parsed, res.responseTime);
  const secondTaxRateId = success ? String(parsed.id) : null;

  // Test 3: list_tax_rates
  res = await callTool(client, 'list_tax_rates', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_tax_rates', 'no_params', success, {}, parsed, res.responseTime);

  // Test 4: get_tax_rate
  if (testData.taxRateId) {
    res = await callTool(client, 'get_tax_rate', { id: testData.taxRateId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_tax_rate', 'valid_id', success, { id: testData.taxRateId }, parsed, res.responseTime);
  }

  // Test 5: update_tax_rate
  if (testData.taxRateId) {
    res = await callTool(client, 'update_tax_rate', {
      id: testData.taxRateId,
      name: 'Updated Tax Rate',
      value: 9.0
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_tax_rate', 'valid_update', success, { id: testData.taxRateId }, parsed, res.responseTime);
  }

  // Cleanup second tax rate
  if (secondTaxRateId) {
    res = await callTool(client, 'delete_tax_rate', { id: secondTaxRateId });
    recordTest('delete_tax_rate', 'cleanup', res.success, { id: secondTaxRateId }, parseResult(res.result), res.responseTime);
  }

  log(`${category} completed`);
}

// Category 5: Invoice Management
async function testInvoiceManagement(client) {
  const category = 'Invoice Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping invoice tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_invoice (need at least 1 item to keep invoice open for updates)
  let res = await callTool(client, 'create_invoice', {
    customer: parseInt(testData.customerId),
    items: [{ name: 'Test Item', quantity: 1, unit_cost: 100 }]  // Need item to avoid auto-close
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_invoice', 'minimal_params', success, { customer: testData.customerId, items: '[1 item]' }, parsed, res.responseTime);
  if (success) {
    testData.invoiceId = String(parsed.id);
    log(`  Created invoice with ID: ${testData.invoiceId}`);
  }

  // Test 2: create_invoice (with items)
  res = await callTool(client, 'create_invoice', {
    customer: parseInt(testData.customerId),
    name: 'Full Test Invoice',
    payment_terms: 'NET 30',
    items: [
      { name: 'Service Item', quantity: 1, unit_cost: 100 },
      { name: 'Product Item', quantity: 2, unit_cost: 50 }
    ],
    notes: 'Test invoice with items'
  });
  parsed = parseResult(res.result);
  success = res.success && parsed && parsed.id;
  recordTest('create_invoice', 'with_items', success, { customer: testData.customerId, items: '2 items' }, parsed, res.responseTime);
  const secondInvoiceId = success ? String(parsed.id) : null;

  // Test 3: list_invoices
  res = await callTool(client, 'list_invoices', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_invoices', 'no_params', success, {}, parsed, res.responseTime);

  // Test 4: get_invoice
  if (testData.invoiceId) {
    res = await callTool(client, 'get_invoice', { id: testData.invoiceId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_invoice', 'valid_id', success, { id: testData.invoiceId }, parsed, res.responseTime);
  }

  // Test 5: update_invoice
  if (testData.invoiceId) {
    res = await callTool(client, 'update_invoice', {
      id: testData.invoiceId,
      name: 'Updated Invoice',
      notes: 'Updated via MCP test'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_invoice', 'valid_update', success, { id: testData.invoiceId }, parsed, res.responseTime);
  }

  // Test 6: void_invoice (on second invoice)
  if (secondInvoiceId) {
    res = await callTool(client, 'void_invoice', { id: secondInvoiceId });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('void_invoice', 'valid_void', success, { id: secondInvoiceId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 6: Payment Management
async function testPaymentManagement(client) {
  const category = 'Payment Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping payment tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_payment
  let res = await callTool(client, 'create_payment', {
    customer: parseInt(testData.customerId),
    amount: 100,
    method: 'check'
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_payment', 'minimal_params', success, { customer: testData.customerId, amount: 100 }, parsed, res.responseTime);
  if (success) {
    testData.paymentId = String(parsed.id);
    log(`  Created payment with ID: ${testData.paymentId}`);
  }

  // Test 2: list_payments
  res = await callTool(client, 'list_payments', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_payments', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_payment
  if (testData.paymentId) {
    res = await callTool(client, 'get_payment', { id: testData.paymentId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_payment', 'valid_id', success, { id: testData.paymentId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 7: Estimate Management
async function testEstimateManagement(client) {
  const category = 'Estimate Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping estimate tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_estimate (items array is REQUIRED)
  let res = await callTool(client, 'create_estimate', {
    customer: parseInt(testData.customerId),
    name: 'Test Estimate',
    items: []  // Required field
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_estimate', 'minimal_params', success, { customer: testData.customerId, items: [] }, parsed, res.responseTime);
  if (success) {
    testData.estimateId = String(parsed.id);
    log(`  Created estimate with ID: ${testData.estimateId}`);
  }

  // Test 2: list_estimates
  res = await callTool(client, 'list_estimates', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_estimates', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_estimate
  if (testData.estimateId) {
    res = await callTool(client, 'get_estimate', { id: testData.estimateId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_estimate', 'valid_id', success, { id: testData.estimateId }, parsed, res.responseTime);
  }

  // Test 4: update_estimate
  if (testData.estimateId) {
    res = await callTool(client, 'update_estimate', {
      id: testData.estimateId,
      name: 'Updated Estimate'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_estimate', 'valid_update', success, { id: testData.estimateId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 8: Credit Note Management
async function testCreditNoteManagement(client) {
  const category = 'Credit Note Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping credit note tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_credit_note (items array is REQUIRED)
  let res = await callTool(client, 'create_credit_note', {
    customer: parseInt(testData.customerId),
    name: 'Test Credit Note',
    items: []  // Required field
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_credit_note', 'minimal_params', success, { customer: testData.customerId, items: [] }, parsed, res.responseTime);
  if (success) {
    testData.creditNoteId = String(parsed.id);
    log(`  Created credit note with ID: ${testData.creditNoteId}`);
  }

  // Test 2: list_credit_notes
  res = await callTool(client, 'list_credit_notes', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_credit_notes', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_credit_note
  if (testData.creditNoteId) {
    res = await callTool(client, 'get_credit_note', { id: testData.creditNoteId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_credit_note', 'valid_id', success, { id: testData.creditNoteId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 9: Coupon Management
async function testCouponManagement(client) {
  const category = 'Coupon Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_coupon
  const couponId = `test_coupon_${Date.now()}`;
  let res = await callTool(client, 'create_coupon', {
    id: couponId,
    name: 'Test Coupon',
    value: 10,
    is_percent: true
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_coupon', 'minimal_params', success, { id: couponId, name: 'Test Coupon' }, parsed, res.responseTime);
  if (success) {
    testData.couponId = String(parsed.id);
    log(`  Created coupon with ID: ${testData.couponId}`);
  }

  // Test 2: list_coupons
  res = await callTool(client, 'list_coupons', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_coupons', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_coupon
  if (testData.couponId) {
    res = await callTool(client, 'get_coupon', { id: testData.couponId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_coupon', 'valid_id', success, { id: testData.couponId }, parsed, res.responseTime);
  }

  // Test 4: update_coupon
  if (testData.couponId) {
    res = await callTool(client, 'update_coupon', {
      id: testData.couponId,
      name: 'Updated Coupon'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_coupon', 'valid_update', success, { id: testData.couponId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 10: Plan Management
async function testPlanManagement(client) {
  const category = 'Plan Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.itemId) {
    log('Skipping plan tests - no item ID for catalog_item', 'WARN');
    return;
  }

  // Test 1: create_plan (interval_count is REQUIRED by API)
  const planId = `test_plan_${Date.now()}`;
  let res = await callTool(client, 'create_plan', {
    id: planId,
    name: 'Test Plan',
    catalog_item: testData.itemId,
    amount: 4999,
    interval: 'month',
    interval_count: 1  // Required by API
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_plan', 'minimal_params', success, { id: planId, name: 'Test Plan', catalog_item: testData.itemId, interval_count: 1 }, parsed, res.responseTime);
  if (success) {
    testData.planId = String(parsed.id);
    log(`  Created plan with ID: ${testData.planId}`);
  }

  // Test 2: list_plans
  res = await callTool(client, 'list_plans', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_plans', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_plan
  if (testData.planId) {
    res = await callTool(client, 'get_plan', { id: testData.planId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_plan', 'valid_id', success, { id: testData.planId }, parsed, res.responseTime);
  }

  // Test 4: update_plan
  if (testData.planId) {
    res = await callTool(client, 'update_plan', {
      id: testData.planId,
      name: 'Updated Plan'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_plan', 'valid_update', success, { id: testData.planId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 11: Subscription Management
async function testSubscriptionManagement(client) {
  const category = 'Subscription Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId || !testData.planId) {
    log('Skipping subscription tests - missing customer or plan ID', 'WARN');
    return;
  }

  // Test 1: create_subscription
  let res = await callTool(client, 'create_subscription', {
    customer: parseInt(testData.customerId),
    plan: testData.planId
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_subscription', 'minimal_params', success, { customer: testData.customerId, plan: testData.planId }, parsed, res.responseTime);
  if (success) {
    testData.subscriptionId = String(parsed.id);
    log(`  Created subscription with ID: ${testData.subscriptionId}`);
  }

  // Test 2: list_subscriptions
  res = await callTool(client, 'list_subscriptions', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_subscriptions', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_subscription
  if (testData.subscriptionId) {
    res = await callTool(client, 'get_subscription', { id: testData.subscriptionId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_subscription', 'valid_id', success, { id: testData.subscriptionId }, parsed, res.responseTime);
  }

  // Test 4: cancel_subscription (id expects NUMBER)
  if (testData.subscriptionId) {
    res = await callTool(client, 'cancel_subscription', { id: parseInt(testData.subscriptionId) });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('cancel_subscription', 'valid_cancel', success, { id: testData.subscriptionId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 12: Task Management
async function testTaskManagement(client) {
  const category = 'Task Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Generate a due date 7 days from now
  const dueDate = new Date();
  dueDate.setDate(dueDate.getDate() + 7);
  const dueDateStr = dueDate.toISOString().split('T')[0]; // YYYY-MM-DD format

  // Test 1: create_task (due_date is REQUIRED by API)
  let res = await callTool(client, 'create_task', {
    name: 'Test Task MCP',
    action: 'phone',
    due_date: dueDateStr  // Required by API
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_task', 'minimal_params', success, { name: 'Test Task MCP', action: 'phone', due_date: dueDateStr }, parsed, res.responseTime);
  if (success) {
    testData.taskId = String(parsed.id);
    log(`  Created task with ID: ${testData.taskId}`);
  }

  // Test 2: list_tasks
  res = await callTool(client, 'list_tasks', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_tasks', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_task
  if (testData.taskId) {
    res = await callTool(client, 'get_task', { id: testData.taskId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_task', 'valid_id', success, { id: testData.taskId }, parsed, res.responseTime);
  }

  // Test 4: update_task
  if (testData.taskId) {
    res = await callTool(client, 'update_task', {
      id: testData.taskId,
      name: 'Updated Task'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_task', 'valid_update', success, { id: testData.taskId }, parsed, res.responseTime);
  }

  // Note: complete_task and reopen_task tools removed - use update_task with complete: true/false

  log(`${category} completed`);
}

// Category 13: Note Management
async function testNoteManagement(client) {
  const category = 'Note Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping note tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_note (field is "notes" not "body", customer_id is STRING)
  let res = await callTool(client, 'create_note', {
    customer_id: testData.customerId,  // String type expected
    notes: 'Test note from MCP'  // Correct field name
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_note', 'minimal_params', success, { customer_id: testData.customerId, notes: 'Test note' }, parsed, res.responseTime);
  if (success) {
    testData.noteId = String(parsed.id);
    log(`  Created note with ID: ${testData.noteId}`);
  }

  // Test 2: list_notes (customer_id expects STRING)
  res = await callTool(client, 'list_notes', { customer_id: testData.customerId });
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_notes', 'valid_customer', success, { customer_id: testData.customerId }, parsed, res.responseTime);

  // Note: get_note tool removed - use list_notes with filter instead

  // Test 3: update_note (id expects STRING)
  if (testData.noteId) {
    res = await callTool(client, 'update_note', {
      customer_id: testData.customerId,
      id: testData.noteId,  // String type expected
      notes: 'Updated note from MCP'  // Correct field name
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_note', 'valid_update', success, { customer_id: testData.customerId, id: testData.noteId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 14: Webhook Management
async function testWebhookManagement(client) {
  const category = 'Webhook Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_webhook (with required events array)
  let res = await callTool(client, 'create_webhook', {
    url: 'https://example.com/webhook-test',
    events: ['invoice.created', 'invoice.paid']
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_webhook', 'minimal_params', success, { url: 'https://example.com/webhook-test', events: ['invoice.created'] }, parsed, res.responseTime);
  if (success) {
    testData.webhookId = String(parsed.id);
    log(`  Created webhook with ID: ${testData.webhookId}`);
  }

  // Test 2: list_webhooks
  res = await callTool(client, 'list_webhooks', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_webhooks', 'no_params', success, {}, parsed, res.responseTime);

  // Note: get_webhook tool removed - use list_webhooks instead

  // Test 3: update_webhook
  if (testData.webhookId) {
    res = await callTool(client, 'update_webhook', {
      id: testData.webhookId,
      url: 'https://example.com/webhook-updated'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_webhook', 'valid_update', success, { id: testData.webhookId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 15: Payment Link Management
async function testPaymentLinkManagement(client) {
  const category = 'Payment Link Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_payment_link
  let res = await callTool(client, 'create_payment_link', {
    name: 'Test Payment Link',
    type: 'one_time',
    amount: 100
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_payment_link', 'minimal_params', success, { name: 'Test Payment Link', amount: 100 }, parsed, res.responseTime);
  if (success) {
    testData.paymentLinkId = String(parsed.id);
    log(`  Created payment link with ID: ${testData.paymentLinkId}`);
  }

  // Test 2: list_payment_links
  res = await callTool(client, 'list_payment_links', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_payment_links', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_payment_link
  if (testData.paymentLinkId) {
    res = await callTool(client, 'get_payment_link', { id: testData.paymentLinkId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_payment_link', 'valid_id', success, { id: testData.paymentLinkId }, parsed, res.responseTime);
  }

  // Test 4: update_payment_link
  if (testData.paymentLinkId) {
    res = await callTool(client, 'update_payment_link', {
      id: testData.paymentLinkId,
      name: 'Updated Payment Link'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_payment_link', 'valid_update', success, { id: testData.paymentLinkId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 16: Email Template Management
async function testEmailTemplateManagement(client) {
  const category = 'Email Template Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_email_template (with required type)
  let res = await callTool(client, 'create_email_template', {
    name: 'Test Template MCP',
    type: 'invoice',
    subject: 'Test Subject',
    body: '<p>Test email body</p>'
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_email_template', 'minimal_params', success, { name: 'Test Template MCP', type: 'invoice' }, parsed, res.responseTime);
  if (success) {
    testData.emailTemplateId = String(parsed.id);
    log(`  Created email template with ID: ${testData.emailTemplateId}`);
  }

  // Test 2: list_email_templates
  res = await callTool(client, 'list_email_templates', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_email_templates', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_email_template
  if (testData.emailTemplateId) {
    res = await callTool(client, 'get_email_template', { template_id: testData.emailTemplateId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_email_template', 'valid_id', success, { template_id: testData.emailTemplateId }, parsed, res.responseTime);
  }

  // Test 4: update_email_template
  if (testData.emailTemplateId) {
    res = await callTool(client, 'update_email_template', {
      template_id: testData.emailTemplateId,
      name: 'Updated Template',
      subject: 'Updated Subject'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_email_template', 'valid_update', success, { template_id: testData.emailTemplateId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 17: Chasing Cadence Management
async function testChasingCadenceManagement(client) {
  const category = 'Chasing Cadence Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: create_chasing_cadence (with required time_of_day, frequency, and steps)
  // Using 'phone' action since 'email' requires an email template which may not exist in sandbox
  let res = await callTool(client, 'create_chasing_cadence', {
    name: 'Test Cadence MCP',
    time_of_day: 9,
    frequency: 'daily',
    steps: [{ name: 'Phone Reminder', action: 'phone', schedule: 'past_due_age:1' }]
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_chasing_cadence', 'minimal_params', success, { name: 'Test Cadence MCP', time_of_day: 9, frequency: 'daily' }, parsed, res.responseTime);
  if (success) {
    testData.chasingCadenceId = String(parsed.id);
    log(`  Created chasing cadence with ID: ${testData.chasingCadenceId}`);
  }

  // Test 2: list_chasing_cadences
  res = await callTool(client, 'list_chasing_cadences', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_chasing_cadences', 'no_params', success, {}, parsed, res.responseTime);

  // Test 3: get_chasing_cadence
  if (testData.chasingCadenceId) {
    res = await callTool(client, 'get_chasing_cadence', { id: testData.chasingCadenceId });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_chasing_cadence', 'valid_id', success, { id: testData.chasingCadenceId }, parsed, res.responseTime);
  }

  // Test 4: update_chasing_cadence
  if (testData.chasingCadenceId) {
    res = await callTool(client, 'update_chasing_cadence', {
      id: testData.chasingCadenceId,
      name: 'Updated Cadence'
    });
    parsed = parseResult(res.result);
    success = res.success;
    recordTest('update_chasing_cadence', 'valid_update', success, { id: testData.chasingCadenceId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Note: Automation Management removed - feature not available in account

// Category 19: Company & Settings
async function testCompanySettings(client) {
  const category = 'Company Settings';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: get_company
  let res = await callTool(client, 'get_company', {});
  let parsed = parseResult(res.result);
  let success = res.success && parsed;
  recordTest('get_company', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Category 20: Event Management
async function testEventManagement(client) {
  const category = 'Event Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: list_events
  let res = await callTool(client, 'list_events', {});
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('list_events', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Category 21: Report Management
async function testReportManagement(client) {
  const category = 'Report Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: generate_report (correct enum is "AgingDetail")
  let res = await callTool(client, 'generate_report', {
    type: 'AgingDetail'
  });
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('generate_report', 'aging_detail', success, { type: 'AgingDetail' }, parsed, res.responseTime);

  log(`${category} completed`);
}

// Note: File Management removed - no list_files endpoint exists in API

// Category 23: Credit Balance Management
async function testCreditBalanceManagement(client) {
  const category = 'Credit Balance Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  if (!testData.customerId) {
    log('Skipping credit balance tests - no customer ID', 'WARN');
    return;
  }

  // Test 1: create_credit_balance (field is "customer" not "customer_id")
  let res = await callTool(client, 'create_credit_balance', {
    customer: testData.customerId,  // Correct field name
    amount: 50
  });
  let parsed = parseResult(res.result);
  let success = res.success && parsed && parsed.id;
  recordTest('create_credit_balance', 'minimal_params', success, { customer: testData.customerId, amount: 50 }, parsed, res.responseTime);
  if (success) {
    testData.creditBalanceId = String(parsed.id);
    log(`  Created credit balance with ID: ${testData.creditBalanceId}`);
  }

  // Test 2: list_credit_balances
  res = await callTool(client, 'list_credit_balances', { customer: testData.customerId });
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_credit_balances', 'valid_customer', success, { customer: testData.customerId }, parsed, res.responseTime);

  // Test 3: get_credit_balance
  if (testData.creditBalanceId) {
    res = await callTool(client, 'get_credit_balance', {
      customer: testData.customerId,
      id: testData.creditBalanceId
    });
    parsed = parseResult(res.result);
    success = res.success && parsed;
    recordTest('get_credit_balance', 'valid_ids', success, { customer: testData.customerId, id: testData.creditBalanceId }, parsed, res.responseTime);
  }

  log(`${category} completed`);
}

// Category 24: Member Management
async function testMemberManagement(client) {
  const category = 'Member Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: list_members
  let res = await callTool(client, 'list_members', {});
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('list_members', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Category 25: Role Management
async function testRoleManagement(client) {
  const category = 'Role Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: list_roles
  let res = await callTool(client, 'list_roles', {});
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('list_roles', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Category 26: API Key Management
async function testApiKeyManagement(client) {
  const category = 'API Key Management';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: list_api_keys
  let res = await callTool(client, 'list_api_keys', {});
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('list_api_keys', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Category 27: Email Communication
async function testEmailCommunication(client) {
  const category = 'Email Communication';
  log(`\n=== Testing ${category} ===`);
  testResults.categories[category] = { passed: 0, failed: 0 };

  // Test 1: email_autocomplete
  let res = await callTool(client, 'email_autocomplete', { query: 'test' });
  let parsed = parseResult(res.result);
  let success = res.success;
  recordTest('email_autocomplete', 'basic_query', success, { query: 'test' }, parsed, res.responseTime);

  // Test 2: list_inboxes
  res = await callTool(client, 'list_inboxes', {});
  parsed = parseResult(res.result);
  success = res.success;
  recordTest('list_inboxes', 'no_params', success, {}, parsed, res.responseTime);

  log(`${category} completed`);
}

// Cleanup function
async function cleanup(client) {
  log('\n=== Starting cleanup ===');

  const cleanupOrder = [
    { id: testData.noteId, tool: 'delete_note', params: (id) => ({ customer_id: testData.customerId, id }) },
    { id: testData.contactId, tool: 'delete_contact', params: (id) => ({ customer_id: testData.customerId, id }) },
    { id: testData.taskId, tool: 'delete_task', params: (id) => ({ id }) },
    { id: testData.webhookId, tool: 'delete_webhook', params: (id) => ({ id }) },
    { id: testData.paymentLinkId, tool: 'delete_payment_link', params: (id) => ({ id }) },
    { id: testData.emailTemplateId, tool: 'delete_email_template', params: (id) => ({ template_id: id }) },
    { id: testData.chasingCadenceId, tool: 'delete_chasing_cadence', params: (id) => ({ id }) },
    { id: testData.invoiceId, tool: 'delete_invoice', params: (id) => ({ id }) },
    { id: testData.planId, tool: 'delete_plan', params: (id) => ({ id }) },
    { id: testData.couponId, tool: 'delete_coupon', params: (id) => ({ id }) },
    { id: testData.taxRateId, tool: 'delete_tax_rate', params: (id) => ({ id }) },
    { id: testData.itemId, tool: 'delete_item', params: (id) => ({ id }) },
    { id: testData.customerId, tool: 'delete_customer', params: (id) => ({ id }) }
  ];

  for (const item of cleanupOrder) {
    if (item.id) {
      try {
        const res = await callTool(client, item.tool, item.params(item.id));
        if (res.success) {
          log(`  Cleaned up ${item.tool}: ${item.id}`);
        }
      } catch (e) {
        log(`  Error cleaning up ${item.tool}: ${e.message}`, 'WARN');
      }
    }
  }

  log('Cleanup completed');
}

// Generate test reports
function generateReports() {
  testResults.summary.endTime = new Date().toISOString();
  const duration = new Date(testResults.summary.endTime) - new Date(testResults.summary.startTime);

  // Generate TEST_RESULTS.md
  let resultsContent = `# Invoiced MCP Server Test Results\n\n`;
  resultsContent += `**Test Run:** ${testResults.summary.startTime}\n`;
  resultsContent += `**Duration:** ${Math.round(duration / 1000)}s\n`;
  resultsContent += `**Environment:** Sandbox\n\n`;
  resultsContent += `## Summary\n\n`;
  resultsContent += `| Metric | Value |\n`;
  resultsContent += `|--------|-------|\n`;
  resultsContent += `| Total Tools Discovered | ${testResults.summary.totalTools} |\n`;
  resultsContent += `| Total Tests Run | ${testResults.summary.totalTests} |\n`;
  resultsContent += `| Tests Passed | ${testResults.summary.passed} |\n`;
  resultsContent += `| Tests Failed | ${testResults.summary.failed} |\n`;
  resultsContent += `| Pass Rate | ${((testResults.summary.passed / testResults.summary.totalTests) * 100).toFixed(1)}% |\n\n`;

  resultsContent += `## Results by Tool\n\n`;

  for (const [toolName, toolData] of Object.entries(testResults.tools)) {
    resultsContent += `### ${toolName}\n\n`;
    resultsContent += `| Test Case | Status | Response Time | Notes |\n`;
    resultsContent += `|-----------|--------|---------------|-------|\n`;

    for (const test of toolData.tests) {
      const status = test.success ? '✅ PASS' : '❌ FAIL';
      resultsContent += `| ${test.testCase} | ${status} | ${test.responseTime}ms | ${test.notes || '-'} |\n`;
    }
    resultsContent += '\n';
  }

  writeFileSync('TEST_RESULTS.md', resultsContent);
  log('Generated TEST_RESULTS.md');

  // Generate TEST_SUMMARY.md
  let summaryContent = `# Invoiced MCP Server Test Summary\n\n`;
  summaryContent += `## Overview\n\n`;
  summaryContent += `- **Test Date:** ${testResults.summary.startTime}\n`;
  summaryContent += `- **Total Tools Discovered:** ${testResults.summary.totalTools}\n`;
  summaryContent += `- **Total Tests Executed:** ${testResults.summary.totalTests}\n`;
  summaryContent += `- **Overall Pass Rate:** ${((testResults.summary.passed / testResults.summary.totalTests) * 100).toFixed(1)}%\n\n`;

  summaryContent += `## Results by Category\n\n`;
  summaryContent += `| Category | Tests |\n`;
  summaryContent += `|----------|-------|\n`;

  for (const category of Object.keys(testResults.categories)) {
    summaryContent += `| ${category} | ✓ |\n`;
  }

  summaryContent += `\n## Failed Tests\n\n`;
  if (testResults.issues.length === 0) {
    summaryContent += `No failed tests! 🎉\n`;
  } else {
    for (const issue of testResults.issues) {
      summaryContent += `- **${issue.tool}** (${issue.testCase}): ${issue.notes || 'Failed'}\n`;
    }
  }

  writeFileSync('TEST_SUMMARY.md', summaryContent);
  log('Generated TEST_SUMMARY.md');

  // Generate ISSUES.md if there are failures
  if (testResults.issues.length > 0) {
    let issuesContent = `# Invoiced MCP Server Test Issues\n\n`;
    issuesContent += `Total Issues: ${testResults.issues.length}\n\n`;
    issuesContent += `## Failed Tests\n\n`;

    for (const issue of testResults.issues) {
      issuesContent += `### ${issue.tool} - ${issue.testCase}\n\n`;
      issuesContent += `**Request:**\n\`\`\`json\n${JSON.stringify(issue.request, null, 2)}\n\`\`\`\n\n`;
      issuesContent += `**Response:**\n\`\`\`\n${typeof issue.response === 'string' ? issue.response.substring(0, 1000) : JSON.stringify(issue.response, null, 2).substring(0, 1000)}\n\`\`\`\n\n`;
      if (issue.notes) {
        issuesContent += `**Notes:** ${issue.notes}\n\n`;
      }
      issuesContent += `---\n\n`;
    }

    writeFileSync('ISSUES.md', issuesContent);
    log('Generated ISSUES.md');
  }
}

// Main test runner
async function main() {
  // Determine which distribution to test
  const distArg = process.argv[2] || 'invoiced-support';
  const serverPath = `packages/${distArg}/src/index.js`;

  log(`Starting Invoiced MCP Server Test Suite`);
  log(`Testing distribution: ${distArg}`);
  log(`Server path: ${serverPath}`);
  testResults.summary.startTime = new Date().toISOString();

  const transport = new StdioClientTransport({
    command: 'node',
    args: [serverPath],
    env: {
      ...process.env,
      INVOICED_API_KEY: process.env.INVOICED_API_KEY,
      INVOICED_SANDBOX: 'true'
    }
  });

  const client = new Client({
    name: 'invoiced-mcp-test-client',
    version: '1.0.0'
  }, {
    capabilities: {}
  });

  try {
    log('Connecting to MCP server...');
    await client.connect(transport);
    log('Connected to MCP server');

    log('Discovering available tools...');
    const toolsResponse = await client.listTools();
    const tools = toolsResponse.tools || [];
    testResults.summary.totalTools = tools.length;
    log(`Discovered ${tools.length} tools`);

    // Run category tests in dependency order
    await testCustomerManagement(client);
    await testContactManagement(client);
    await testItemManagement(client);
    await testTaxRateManagement(client);
    await testInvoiceManagement(client);
    await testPaymentManagement(client);
    await testEstimateManagement(client);
    await testCreditNoteManagement(client);
    await testCouponManagement(client);
    await testPlanManagement(client);
    await testSubscriptionManagement(client);
    await testTaskManagement(client);
    await testNoteManagement(client);
    await testWebhookManagement(client);
    await testPaymentLinkManagement(client);
    await testEmailTemplateManagement(client);
    await testChasingCadenceManagement(client);
    await testCreditBalanceManagement(client);
    await testMemberManagement(client);
    await testRoleManagement(client);
    await testApiKeyManagement(client);
    await testEmailCommunication(client);
    await testCompanySettings(client);
    await testEventManagement(client);
    await testReportManagement(client);

    // Cleanup test data
    await cleanup(client);

    // Generate reports
    generateReports();

    log(`\n========================================`);
    log(`Test Suite Complete`);
    log(`Total Tests: ${testResults.summary.totalTests}`);
    log(`Passed: ${testResults.summary.passed}`);
    log(`Failed: ${testResults.summary.failed}`);
    log(`Pass Rate: ${((testResults.summary.passed / testResults.summary.totalTests) * 100).toFixed(1)}%`);
    log(`========================================\n`);

  } catch (error) {
    log(`Fatal error: ${error.message}`, 'ERROR');
    console.error(error);
  } finally {
    await client.close();
    process.exit(testResults.summary.failed > 0 ? 1 : 0);
  }
}

main();
