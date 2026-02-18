import { z } from "zod";

export const reportTools = [
  {
    name: "generate_report",
    description: "Generate a report. Use POST to create, then GET /reports/{id} to retrieve results.",
    inputSchema: z.object({
      type: z.enum([
        "AgingDetail", "AgingSummary", "InstallmentAgingDetail", "InstallmentAgingSummary",
        "PaymentSummary", "SalesSummary", "EstimateSummary", "CreditSummary", "MerchantAccountSummary",
        "AROverview", "Arr", "PaymentStatistics", "InvoicedPaymentsDetail", "InvoicedPaymentsSummary",
        "FailedCharges", "Refunds", "ChasingActivity", "Mrr", "MrrMovements", "NetRevenueRetention",
        "SubscriptionChurn", "TotalSubscribers", "TaxSummary", "SalesByItem", "CashFlow",
        "Reconciliation", "LateFees", "LifetimeValue", "BadDebt"
      ]).describe("Report type"),
      start_date: z.number().describe("Report start date (Unix timestamp)").optional(),
      end_date: z.number().describe("Report end date (Unix timestamp)").optional(),
      filters: z.object({
        customer_id: z.number().describe("Filter by customer ID").optional(),
        status: z.string().describe("Filter by status").optional(),
        currency: z.string().describe("Filter by currency").optional(),
      }).describe("Additional filters specific to report type").optional(),
    }),
  },
  {
    name: "get_report",
    description: "Get a generated report by ID",
    inputSchema: z.object({
      id: z.string().describe("Report ID"),
    }),
  },
  {
    name: "list_scheduled_reports",
    description: "List all scheduled reports",
    inputSchema: z.object({}),
  },
  {
    name: "create_scheduled_report",
    description: "Create a scheduled report",
    inputSchema: z.object({
      name: z.string().describe("Report name"),
      type: z.string().describe("Report type"),
      schedule: z.enum(["daily", "weekly", "monthly", "quarterly", "yearly"]).describe("Schedule frequency"),
      recipients: z.array(z.string()).describe("Email addresses to send report to"),
      format: z.enum(["csv", "pdf", "xlsx"]).describe("Report format").optional(),
      filters: z.object({}).describe("Report filters").optional(),
    }),
  },
  {
    name: "update_scheduled_report",
    description: "Update a scheduled report",
    inputSchema: z.object({
      id: z.string().describe("Scheduled report ID"),
      schedule: z.enum(["daily", "weekly", "monthly", "quarterly", "yearly"]).describe("Schedule frequency").optional(),
      recipients: z.array(z.string()).describe("Email addresses").optional(),
      enabled: z.boolean().describe("Whether the schedule is active").optional(),
    }),
  },
  {
    name: "delete_scheduled_report",
    description: "Delete a scheduled report",
    inputSchema: z.object({
      id: z.string().describe("Scheduled report ID"),
    }),
  },
];
