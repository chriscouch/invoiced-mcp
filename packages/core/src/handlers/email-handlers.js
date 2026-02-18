export const emailHandlers = {
  async list_inboxes(invoiced, args) {
    const result = await invoiced.listInboxes(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_inbox(invoiced, args) {
    const result = await invoiced.getInbox(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async send_email(invoiced, args) {
    const { inbox_id, ...emailData } = args;
    const result = await invoiced.sendEmail(inbox_id, emailData);
    return {
      content: [
        {
          type: "text",
          text: `Email sent successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async list_inbox_threads(invoiced, args) {
    const { inbox_id, ...params } = args;
    return await invoiced.listInboxThreads(inbox_id, params);
  },  async get_email_thread(invoiced, args) {
    const result = await invoiced.getEmailThread(args.thread_id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_email_thread(invoiced, args) {
    const { thread_id, ...threadData } = args;
    const result = await invoiced.updateEmailThread(thread_id, threadData);
    return {
      content: [
        {
          type: "text",
          text: `Email thread updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async get_email_thread_by_document(invoiced, args) {
    return await invoiced.getEmailThreadByDocument(args.document_type, args.document_id);
  },

  async list_inbox_emails(invoiced, args) {
    const { inbox_id, ...params } = args;
    return await invoiced.listInboxEmails(inbox_id, params);
  },

  async list_thread_emails(invoiced, args) {
    const { thread_id, ...params } = args;
    return await invoiced.listThreadEmails(thread_id, params);
  },  async get_inbox_email(invoiced, args) {
    const result = await invoiced.getInboxEmail(args.email_id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_email_message(invoiced, args) {
    const result = await invoiced.getEmailMessage(args.email_id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async list_thread_notes(invoiced, args) {
    const { thread_id, ...params } = args;
    return await invoiced.listThreadNotes(thread_id, params);
  },

  async create_thread_note(invoiced, args) {
    const { thread_id, ...noteData } = args;
    const result = await invoiced.createThreadNote(thread_id, noteData);
    return {
      content: [
        {
          type: "text",
          text: `Thread note created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async list_email_attachments(invoiced, args) {
    const { email_id, ...params } = args;
    return await invoiced.listEmailAttachments(email_id, params);
  },

  async email_autocomplete(invoiced, args) {
    return await invoiced.emailAutocomplete(args);
  },
};