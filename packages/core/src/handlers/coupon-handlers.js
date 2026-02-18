export const couponHandlers = {
  async list_coupons(invoiced, args) {
    const result = await invoiced.listCoupons(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_coupon(invoiced, args) {
    const result = await invoiced.getCoupon(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_coupon(invoiced, args) {
    const result = await invoiced.createCoupon(args);
    return {
      content: [
        {
          type: "text",
          text: `Coupon created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_coupon(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateCoupon(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Coupon updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_coupon(invoiced, args) {
    const result = await invoiced.deleteCoupon(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Coupon deleted successfully: ${args.id}`,
        },
      ],
    };
  },
};