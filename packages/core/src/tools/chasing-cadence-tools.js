import { z } from "zod";

const stepSchema = z.object({
  name: z.string().describe("Step name"),
  action: z.enum(["email", "sms", "phone", "escalate"]).describe("Action to take"),
  schedule: z.string().describe("Days relative to due date: Use 'age:X' for days after creation or 'past_due_age:X' for days past due"),
  email_template_id: z.string().optional().describe("Email template ID - use standard template names like 'unpaid_invoice_email', 'late_payment_reminder_email' (required for email action)"),
  sms_template_id: z.number().optional().describe("SMS template ID (required for sms action)"),
  assigned_user_id: z.number().optional().describe("User ID to assign escalated tasks to (for escalate action)"),
  role_id: z.number().optional().describe("Contact role ID to target"),
});

export const chasingCadenceTools = [
  {
    name: "list_chasing_cadences",
    description: "List chasing cadences with optional filters",
    inputSchema: z.object({
      per_page: z.number().optional().describe("Number of chasing cadences per page (default: 100, omit to get default)"),
      page: z.number().optional().describe("Page number for pagination"),
      filter: z.object({
        paused: z.boolean().optional().describe("Filter by paused status"),
        assignment_mode: z.enum(["none", "default", "conditions"]).optional().describe("Filter by assignment mode"),
        name: z.string().optional().describe("Filter by cadence name (partial match)"),
      }).optional().describe("Filter criteria using bracket notation"),
      sort: z.string().optional().describe("Sort order (e.g., 'name', '-name' for descending)"),
    }),
  },
  {
    name: "get_chasing_cadence",
    description: "Get a specific chasing cadence by ID",
    inputSchema: z.object({
      id: z.string().describe("Chasing cadence ID"),
    }),
  },
  {
    name: "create_chasing_cadence",
    description: "Create a new chasing cadence. IMPORTANT: At least one step is required.",
    inputSchema: z.object({
      name: z.string().describe("Cadence name"),
      time_of_day: z.number().describe("Hour of day to run cadence (0-23)"),
      frequency: z.enum(["daily", "day_of_week", "day_of_month"]).describe("How often to run the cadence"),
      steps: z.array(stepSchema).min(1).describe("REQUIRED: Cadence steps defining the chasing actions. Must have at least one step."),
      run_date: z.number().optional().describe("Day of month (1-31) for monthly frequency or day of week (0-6) for weekly"),
      run_days: z.string().optional().describe("Days to run for day_of_week frequency (comma-separated: 0=Sunday)"),
      paused: z.boolean().optional().describe("Whether the cadence is paused"),
      min_balance: z.number().optional().describe("Minimum balance threshold to trigger chasing"),
      assignment_mode: z.enum(["none", "default", "conditions"]).optional().describe("How to assign customers to this cadence"),
      assignment_conditions: z.string().optional().describe("Conditions for automatic assignment (when assignment_mode is 'conditions')"),
    }),
  },
  {
    name: "update_chasing_cadence",
    description: "Update a chasing cadence",
    inputSchema: z.object({
      id: z.string().describe("Chasing cadence ID"),
      name: z.string().optional().describe("Cadence name"),
      time_of_day: z.number().optional().describe("Hour of day to run cadence (0-23)"),
      frequency: z.enum(["daily", "day_of_week", "day_of_month"]).optional().describe("How often to run the cadence"),
      paused: z.boolean().optional().describe("Whether the cadence is paused"),
      min_balance: z.number().optional().describe("Minimum balance threshold to trigger chasing"),
    }),
  },
  {
    name: "delete_chasing_cadence",
    description: "Delete a chasing cadence. If the chasing cadence has assigned customers, it can't be deleted.",
    inputSchema: z.object({
      id: z.string().describe("Chasing cadence ID"),
    }),
  },
  {
    name: "run_chasing_cadence",
    description: "Manually run a chasing cadence",
    inputSchema: z.object({
      id: z.string().describe("Chasing cadence ID to run"),
    }),
  },
  {
    name: "assign_chasing_cadence",
    description: "Assign customers to a chasing cadence",
    inputSchema: z.object({
      id: z.string().describe("Chasing cadence ID"),
      customer_ids: z.array(z.string()).describe("Array of customer IDs to assign to the cadence"),
    }),
  },
];
