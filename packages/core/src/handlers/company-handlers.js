export const companyHandlers = {
  async get_company(invoiced, args) {
    const result = await invoiced.getCompany();
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_company(invoiced, args) {
    const result = await invoiced.updateCompany(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async list_members(invoiced, args) {
    const result = await invoiced.listMembers(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_member(invoiced, args) {
    const result = await invoiced.getMember(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_member(invoiced, args) {
    const result = await invoiced.createMember(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_member(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateMember(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async delete_member(invoiced, args) {
    const result = await invoiced.deleteMember(args.id);
    return {
      content: [
        {
          type: "text",
          text: result ? "Member deleted successfully" : "Failed to delete member",
        },
      ],
    };
  },

  async list_roles(invoiced, args) {
    const result = await invoiced.listRoles();
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_role(invoiced, args) {
    const result = await invoiced.getRole(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_role(invoiced, args) {
    const result = await invoiced.createRole(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_role(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateRole(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async delete_role(invoiced, args) {
    const result = await invoiced.deleteRole(args.id);
    return {
      content: [
        {
          type: "text",
          text: result ? "Role deleted successfully" : "Failed to delete role",
        },
      ],
    };
  },

  async list_api_keys(invoiced, args) {
    const result = await invoiced.listApiKeys();
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_api_key(invoiced, args) {
    const result = await invoiced.createApiKey(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async delete_api_key(invoiced, args) {
    const result = await invoiced.deleteApiKey(args.id);
    return {
      content: [
        {
          type: "text",
          text: result ? "API key deleted successfully" : "Failed to delete API key",
        },
      ],
    };
  },
};