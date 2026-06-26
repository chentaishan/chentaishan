const api = require('../../utils/api.js');

Page({
  data: { nickname: '用户', inviteCode: '----', avatarUrl: '', posterGenerating: false },
  async onLoad() {
    try {
      const link = await api.getUserInviteInfo();
      this.setData({ inviteCode: link.code, nickname: link.nickname || '用户', avatarUrl: link.avatar || '/assets/avatar_placeholder.png' });
      api.reportEvent('invite_view', {});
    } catch (e) { console.error(e); }
  },

  // 入口：生成并保存海报
  async createAndSavePoster() {
    if (this.data.posterGenerating) return;
    this.setData({ posterGenerating: true });
    try {
      // 1) 拿到用于生成海报的小程序码/二维码图片 URL（由后端生成）
      const qrRes = await api.getInviteQRCode(this.data.inviteCode);
      const qrUrl = qrRes.qrUrl; // 后端返回的可下载二维码图片地址

      // 2) 下载头像 & 二维码并获取本地路径（必须在合法域名列表中）
      const [avatarInfo, qrInfo] = await Promise.all([
        this._getImageInfo(this.data.avatarUrl),
        this._getImageInfo(qrUrl)
      ]);

      // 3) 绘制 canvas 海报
      const canvasId = 'posterCanvas';
      await this._drawPoster(canvasId, {
        width: 750, // 设计稿宽度（rpx 转 px：在小程序 canvas 使用像素单位通常采用 2x 或 750 方案）
        height: 1200,
        avatarPath: avatarInfo.path,
        nickname: this.data.nickname,
        inviteCode: this.data.inviteCode,
        qrPath: qrInfo.path
      });

      // 4) 导出 canvas 文件并保存到相册（含授权流程)
      const tempFile = await this._canvasToTempFile(canvasId);
      await this._saveImageToPhotosAlbum(tempFile.tempFilePath);

      wx.showToast({ title: '海报已保存到相册', icon: 'success' });
      api.reportEvent('save_poster', { inviteCode: this.data.inviteCode });
    } catch (err) {
      console.error('create poster error', err);
      wx.showToast({ title: err.message || '生成失败，请重试', icon: 'none' });
    } finally {
      this.setData({ posterGenerating: false });
    }
  },

  // helper: 获取远程图片本地路径（支持 network 或 本地）
  _getImageInfo(url) {
    return new Promise((resolve, reject) => {
      // 如果是 data:base64，可以先 writeFile 到本地再返回 path（此处以常见网络图片为主）
      wx.getImageInfo({
        src: url,
        success(res) { resolve({ path: res.path, width: res.width, height: res.height }); },
        fail(err) {
          // 尝试 wx.downloadFile 作为后备
          wx.downloadFile({
            url,
            success(dlRes) {
              if (dlRes.statusCode === 200) {
                wx.getImageInfo({
                  src: dlRes.tempFilePath,
                  success(info) { resolve({ path: info.path, width: info.width, height: info.height }); },
                  fail(e) { reject(e); }
                });
              } else reject(new Error('下载图片失败'));
            },
            fail(e) { reject(e); }
          });
        }
      });
    });
  },

  // helper: 绘制海报（简易布局：白底、头像、昵称、邀请码、二维码）
  _drawPoster(canvasId, opts) {
    const { width, height, avatarPath, nickname, inviteCode, qrPath } = opts;
    return new Promise((resolve, reject) => {
      const ctx = wx.createCanvasContext(canvasId, this);

      // 缩放考虑：canvas 真正像素可能与 rpx 不同，这里使用宽度为 750 的设计稿并用相对数值绘制
      // 背景
      ctx.setFillStyle('#FFFFFF');
      ctx.fillRect(0, 0, width, height);

      // 顶部文字
      ctx.setFillStyle('#333');
      ctx.setFontSize(38);
      ctx.fillText('邀请你加入活动', 40, 90);

      // 头像（圆形）和昵称
      const avatarX = 40, avatarY = 120, avatarSize = 140;
      // 画圆裁剪
      ctx.save();
      ctx.beginPath();
      ctx.arc(avatarX + avatarSize / 2, avatarY + avatarSize / 2, avatarSize / 2, 0, 2 * Math.PI);
      ctx.clip();
      ctx.drawImage(avatarPath, avatarX, avatarY, avatarSize, avatarSize);
      ctx.restore();

      ctx.setFillStyle('#333');
      ctx.setFontSize(34);
      ctx.fillText(nickname, avatarX + avatarSize + 24, avatarY + 80);

      // 中间文案
      ctx.setFillStyle('#666');
      ctx.setFontSize(28);
      ctx.fillText('邀请你注册并完成首单，邀请人可获返现奖励', 40, avatarY + avatarSize + 80, width - 80);

      // 二维码区域
      const qrSize = 300;
      const qrX = (width - qrSize) / 2;
      const qrY = avatarY + avatarSize + 140;
      ctx.setFillStyle('#fff');
      ctx.fillRect(qrX - 10, qrY - 10, qrSize + 20, qrSize + 120);

      ctx.drawImage(qrPath, qrX, qrY, qrSize, qrSize);

      // 二维码下方文字：邀请码
      ctx.setFillStyle('#333');
      ctx.setFontSize(32);
      ctx.fillText(`邀请码：${inviteCode}`, qrX, qrY + qrSize + 60);

      // 底部小字
      ctx.setFillStyle('#999');
      ctx.setFontSize(22);
      ctx.fillText('长按识别小程序码，立即注册抢优惠', 40, height - 80);

      // 最后绘制
      ctx.draw(false, () => { // draw callback
        // 小延迟，确保 canvas 已准备好
        setTimeout(() => resolve(), 200);
      });
    });
  },

  // helper: canvas -> tempFile
  _canvasToTempFile(canvasId) {
    return new Promise((resolve, reject) => {
      wx.canvasToTempFilePath({
        canvasId,
        success: (res) => resolve(res),
        fail: (err) => reject(err)
      }, this);
    });
  },

  // helper: 保存图片到相册（含授权）
  _saveImageToPhotosAlbum(tempFilePath) {
    return new Promise((resolve, reject) => {
      wx.getSetting({
        success: (res) => {
          if (res.authSetting && res.authSetting['scope.writePhotosAlbum']) {
            wx.saveImageToPhotosAlbum({
              filePath: tempFilePath,
              success() { resolve(); },
              fail(e) { reject(e); }
            })
          } else {
            // 请求授权
            wx.authorize({
              scope: 'scope.writePhotosAlbum',
              success() {
                wx.saveImageToPhotosAlbum({
                  filePath: tempFilePath,
                  success() { resolve(); },
                  fail(e) { reject(e); }
                })
              },
              fail() {
                // 引导用户打开设置
                wx.showModal({
                  title: '需要授权',
                  content: '保存海报需要您授权访问相册，请在设置中打开“保存到相册”权限。',
                  success(modalRes) {
                    if (modalRes.confirm) {
                      wx.openSetting({
                        success(settingRes) {
                          if (settingRes.authSetting['scope.writePhotosAlbum']) {
                            wx.saveImageToPhotosAlbum({
                              filePath: tempFilePath,
                              success() { resolve(); },
                              fail(e) { reject(e); }
                            });
                          } else {
                            reject(new Error('用户未授权保存到相册'));
                          }
                        }
                      })
                    } else reject(new Error('用户取消授权'));
                  }
                })
              }
            })
          }
        },
        fail(err) { reject(err); }
      })
    });
  },

  // 保留原有 onShareAppMessage，以便用户通过小程序自带分享也能携带 inviter 参数
  onShareAppMessage() {
    const path = `/pages/onboard/onboard?inviter=${this.data.inviteCode}`;
    return {
      title: '加入我，享首单优惠！',
      path,
      imageUrl: '/assets/share_thumb.png'
    }
  }
});
