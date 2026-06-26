const api = require('../../utils/api.js');
Page({
  data: { invitedCount: 0, validCount: 0 },
  async onLoad() {
    try {
      // TODO: fetch progress
      const me = await api.getUser();
      this.setData({ invitedCount: me.invited_count || 0, validCount: me.valid_invite_count || 0 });
      api.reportEvent('landing_view', {});
    } catch (e) {}
  },
  onInvite() {
    wx.navigateTo({ url: '/pages/invite/invite' });
    api.reportEvent('click_invite', {});
  },
  openRewards() { wx.navigateTo({ url: '/pages/rewards/rewards' }); },
  openRules() { wx.navigateTo({ url: '/pages/rules/rules' }); }
})
