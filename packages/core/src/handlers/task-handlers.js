export const taskHandlers = {
  async list_tasks(invoiced, args) {
    const result = await invoiced.listTasks(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_task(invoiced, args) {
    const result = await invoiced.getTask(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_task(invoiced, args) {
    const result = await invoiced.createTask(args);
    return {
      content: [
        {
          type: "text",
          text: `Task created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_task(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateTask(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Task updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_task(invoiced, args) {
    const result = await invoiced.deleteTask(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Task deleted successfully: ${args.id}`,
        },
      ],
    };
  },

  async complete_task(invoiced, args) {
    const result = await invoiced.completeTask(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Task marked as complete:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async reopen_task(invoiced, args) {
    const result = await invoiced.reopenTask(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Task reopened successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};